<?php
/*
Plugin Name: Final Fantasy XIV: ARR - FC Roster
Plugin URI: 
Description: This plugin will fetch the charakter profiles of all FC Members and show them in a table.
Version: 0.1
Author: Andreas Spuling
Author URI: 
*/

    define(TABLE_NAME,'ffxiv_fc_roster');
    
    // function to create the DB / Options / Defaults					
    function plugin_options_install() {
       	global $wpdb;
     
    	$sql = "CREATE TABLE " . $wpdb->prefix.TABLE_NAME . " (
    		`id` int(10) NOT NULL DEFAULT '0',
    		`name` varchar(100) NOT NULL,
            `avatar_url` tinytext,
            `rank` tinytext,
            `gladiator` tinyint(2) NOT NULL DEFAULT '0',
            `marauder` tinyint(2) NOT NULL DEFAULT '0',
            `darkknight` tinyint(2) NOT NULL DEFAULT '0',
            `pugilist` tinyint(2) NOT NULL DEFAULT '0',
            `lancer` tinyint(2) NOT NULL DEFAULT '0',
            'rogue` tinyint(4) NOT NULL DEFAULT '0',
            `archer` tinyint(2) NOT NULL DEFAULT '0',
            `machinist` tinyint(2) NOT NULL DEFAULT '0',
            `thaumaturge` tinyint(2) NOT NULL DEFAULT '0',
            `arcanist` tinyint(2) NOT NULL DEFAULT '0',
            `conjurer` tinyint(2) NOT NULL DEFAULT '0',
            `astrologian` tinyint(2) NOT NULL DEFAULT '0',
            `carpenter` tinyint(2) NOT NULL DEFAULT '0',
            `blacksmith` tinyint(2) NOT NULL DEFAULT '0',
            `armorer` tinyint(2) NOT NULL DEFAULT '0',
            `goldsmith` tinyint(2) NOT NULL DEFAULT '0',
            `leatherworker` tinyint(2) NOT NULL DEFAULT '0',
            `weaver` tinyint(2) NOT NULL DEFAULT '0',
            `alchemist` tinyint(2) NOT NULL DEFAULT '0',
            `culinarian` tinyint(2) NOT NULL DEFAULT '0',
            `miner` tinyint(2) NOT NULL DEFAULT '0',
            `botanist` tinyint(2) NOT NULL DEFAULT '0',
            `fisher` tinyint(2) NOT NULL DEFAULT '0',
            PRIMARY KEY  (`id`)
    		);";
    		
     
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
     
    }
    
    function ffxiv_roster_plugin_settings(){
        add_options_page( 'FFXIV Roster Settings', 'FFXIV Roster Settings', 'administrator', 'ffxiv_roster_settings', 'ffxiv_roster_display_settings');
    }
    
    function ffxiv_roster_display_settings(){
        $fcId = get_option('ffroster_fcid','');
        $html = '<form action="options.php" method="post" name="options">
                <h2>Adjust your settings</h2>
                ' . wp_nonce_field('update-options') . '
                <table class="form-table" width="100%" cellpadding="10">
                    <tbody>
                        <tr>
                        <td scope="row" align="left">
                        <label>Free Company Id: </label><input type="text" name="ffroster_fcid" value="'.$fcId.'"/></td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="ffroster_fcid" />
                <input type="submit" name="Submit" value="Update" /></form>';
        echo $html;
    }

    function ffxiv_roster_update_charakters(){
        global $wpdb;
        $fcId = get_option('ffroster_fcid','');
        require 'lib/XIVPads-LodestoneAPI/api-autoloader.php';
        $api = new Viion\Lodestone\LodestoneAPI();
        $freeCompany = $api->Search->FreeCompany($fcId,true);
        
        if ( $freeCompany != NULL )
        {
            $members = $freeCompany->members;
            $membersById = array();
        	foreach( $members as $member ){
        	    $membersById[$member['id']] = $member;
        	}
            $storedIds = $wpdb->get_col('SELECT id From '.$wpdb->prefix.TABLE_NAME);
            
            //Delete members
            foreach($storedIds as $id){
                if(!array_key_exists($id,$membersById)){
                    $wpdb->delete( $wpdb->prefix.TABLE_NAME, ['id'=> $id]);
                }
            }
            
            //Compare ids and insert/update missing ones
            foreach($membersById as $id=>$member){
                $character = $api->Search->Character( $id );
        		$rank = "<img src='".$member["rank"]["icon"]."' title='".$member["rank"]["title"]."'/>";
        		
        		
        		$sql = array();
        		$sql["id"] = $id;
        		$sql["name"] = "$character->name";
        		$sql["rank"] = "$rank";
        		$sql["avatar_url"] = "$character->avatar";
        		
        		foreach($character->classjobs as $classJob){
        		    
        		    $class = str_replace(" ","",strtolower( $classJob['name']));
        		    $sql[$class] = $classJob['level'];
        		}
        		
        		if(in_array($id,$storedIds)){
                    $wpdb->update( $wpdb->prefix.TABLE_NAME, $sql,['id'=> $id]);
                }
                else{
                    $wpdb->insert( $wpdb->prefix.TABLE_NAME, $sql );
                }
                
            }
            
        }
        spl_autoload_unregister(array(__CLASS__, 'autoload'));
    }

    function ffxiv_roster_callback( $atts ){
        global $wpdb;
        $members = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.TABLE_NAME." ORDER BY rank");
        echo "<table><thead><tr><th>Name</th><th>Rank</th>";
        
        foreach($members[0] as $key=>$value){
            if($key == "id" || $key == "name" || $key == "avatar_url" || $key == "rank"){
                continue;
            }
            else{
                $imagePath = plugins_url( "img/$key.png", __FILE__ );
                echo "<th><img src=$imagePath></th>";
            }
        }
        echo "</tr></thead><tbody>";
        
        foreach($members as $member){
            $nameCell = "<td width='20%' title='$member->name' style='text-align: left;'>
                <img class='members' src='$member->avatar_url'/> 
                <a href=http://eu.finalfantasyxiv.com/lodestone/character/$member->id/ target=_blank>$member->name</a></td>";
            $rank = $member->rank;
            echo "<tr>$nameCell<td>$rank</td>";
            foreach($member as $key=>$value){
                if($key == "id" || $key == "name" || $key == "avatar_url" || $key == "rank"){
                    continue;
                }
                else{
                    echo "<td>$value</td>";
                }
            }
            echo "</tr>"; 
        }
        echo "</tbody></table>";
        
    }
    
    register_activation_hook( __FILE__, 'ffxiv_roster_activation' );
    /**
     * On activation, set a time, frequency and name of an action hook to be scheduled.
     */
    function ffxiv_roster_activation() {
    	wp_schedule_event( time(), 'hourly', 'ffxiv_roster_hourly_event_hook' );
    }
    
    add_action( 'ffxiv_roster_hourly_event_hook', 'ffxiv_roster_update_charakters' );
    
    register_deactivation_hook( __FILE__, 'ffxiv_roster_deactivation' );
    /**
     * On deactivation, remove all functions from the scheduled action hook.
     */
    function ffxiv_roster_deactivation() {
    	wp_clear_scheduled_hook( 'ffxiv_roster_hourly_event_hook' );
    }
    
    
    // run the install scripts upon plugin activation
    register_activation_hook(__FILE__,'plugin_options_install');
    add_action('admin_menu', 'ffxiv_roster_plugin_settings');
    add_shortcode( "ffxiv_roster", "ffxiv_roster_callback" );

?>
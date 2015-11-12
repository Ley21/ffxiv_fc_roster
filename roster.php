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
    define(RANK_TABLE_NAME,'ffxiv_fc_rank');
    
    // function to create the DB / Options / Defaults					
    function plugin_options_install() {
       	global $wpdb;
     
    	$sql = "CREATE TABLE " . $wpdb->prefix.TABLE_NAME . " (
    		`id` int(10) NOT NULL DEFAULT '0',
    		`name` varchar(100) NOT NULL,
            `avatar_url` tinytext,
            `rank_icon` tinytext ,
            `rank_order` int(10),
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
        $onlyDow = get_option('ffroster_only_dow','false');
        global $wpdb;
        $html = '<form action="options.php" method="post" name="options">
                <h2>Adjust your settings</h2>
                ' . wp_nonce_field('update-options') . '
                <table class="form-table" width="100%" cellpadding="10">
                    <tbody>
                        <tr>
                        <td scope="row" align="left">
                        <label>Free Company Id: </label><input type="text" name="ffroster_fcid" value="'.$fcId.'"/></td>
                        </tr>
                        <tr>
                        <td scope="row" align="left">
                        <label>Only Disciplin of War: </label>
                        <select name="ffroster_only_dow">
                            <option value="true" '.($onlyDow == 'true' ? 'selected' : '').'>True</option>
                            <option value="false" '.($onlyDow == 'false' ? 'selected' : '').'>False</option>
                        </select>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="page_options" value="ffroster_fcid" />
                <input type="hidden" name="page_options" value="ffroster_only_dow" />
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
            $ranks = [];
            $rankOrder = 1;
        	foreach( $members as $member ){
        	    if(!in_array($member["rank"],$ranks)){
            		$ranks[$rankOrder] = $member["rank"];
            		$rankOrder++;
        		}
        	    
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
        		$rankTitel = $member["rank"]["title"];
        		$rank = "<img src='".$member["rank"]["icon"]."' title='$rankTitel'/>";
        		
        		$sql = array();
        		$sql["id"] = $id;
        		$sql["name"] = "$character->name";
        		$sql["rank_icon"] = "$rank";
        		$sql["rank_order"] = array_search($member["rank"], $ranks);
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
                
                //break;
            }
            
        }
        spl_autoload_unregister(array(__CLASS__, 'autoload'));
    }

    function ffxiv_roster_callback( $atts ){
        global $wpdb;
        $onlyDow = get_option('ffroster_only_dow','false') == "false" 
            ? false : true;
        
        $members = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.TABLE_NAME." ORDER BY rank_order");
        echo "<center><table class='table table-bordered'><thead><tr><th>Name</th><th>Rank</th>";
        
        foreach($members[0] as $key=>$value){
            if($key == "carpenter" && $onlyDow){
                break;
            }
            if($key == "id" || $key == "name" || $key == "avatar_url" || $key == "rank_icon" || $key == "rank_order"){
                continue;
            }
            else{
                $imagePath = plugins_url( "img/$key.png", __FILE__ );
                echo "<th style='padding: 5px'><img src=$imagePath></th>";
            }
        }
        echo "</tr></thead><tbody>";
        
        foreach($members as $member){
            $nameCell = "<td width='20%' title='$member->name' style='text-align: left;'>
                <img style='height: 50px' class='members' src='$member->avatar_url'/> <br/>
                <a href=http://eu.finalfantasyxiv.com/lodestone/character/$member->id/ target=_blank>$member->name</a></td>";
            $rank = $member->rank_icon;
            echo "<tr><center>$nameCell</center><td><center>$rank</center></td>";
            foreach($member as $key=>$value){
                if($key == "carpenter" && $onlyDow){
                    break;
                }
                if($key == "id" || $key == "name" || $key == "avatar_url" || $key == "rank_icon" || $key == "rank_order"){
                    continue;
                }
                else{
                    echo "<td><center>$value</center></td>";
                }
            }
            echo "</tr>"; 
        }
        echo "</tbody></table></center>";
        
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
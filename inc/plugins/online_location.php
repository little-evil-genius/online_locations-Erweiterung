<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook("admin_rpgstuff_action_handler", "online_location_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "online_location_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "online_location_admin_rpgstuff_menu");
$plugins->add_hook("admin_load", "online_location_admin_manage");
$plugins->add_hook('admin_rpgstuff_update_plugin', 'online_location_admin_update_plugin');
$plugins->add_hook("fetch_wol_activity_end", "online_location_wol_activity");
$plugins->add_hook("build_friendly_wol_location_end", "online_location_wol_location");

// Die Informationen, die im Pluginmanager angezeigt werden
function online_location_info(){
	return array(
		"name"		=> "Online Locations Erweiterung",
		"description"	=> "Erweitert die Wer-ist-Wo/Online-Liste um die Information von eigenen Seiten.",
		"website"	=> "https://github.com/little-evil-genius/online_locations-Erweiterung",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.2",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function online_location_install(){
    
    global $lang;

    // SPRACHDATEI
    $lang->load("online_location");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->online_location_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANK ERSTELLEN
    online_location_database();
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function online_location_is_installed(){

    global $db, $mybb;

    if ($db->table_exists("online_locations")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function online_location_uninstall(){
    
	global $db;

    // DATENBANK LÖSCHEN
    if($db->table_exists("online_locations"))
    {
        $db->drop_table("online_locations");
    }
}

######################
### HOOK FUNCTIONS ###
######################

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function online_location_admin_rpgstuff_action_handler(&$actions) {
	$actions['online_location'] = array('active' => 'online_location', 'file' => 'online_location');
}

// Benutzergruppen-Berechtigungen im ACP
function online_location_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang;
	
    $lang->load('online_location');

	$admin_permissions['online_location'] = $lang->online_location_permission;

	return $admin_permissions;
}

// Menü einfügen
function online_location_admin_rpgstuff_menu(&$sub_menu) {

	global $lang;
	
    $lang->load('online_location');

	$sub_menu[] = [
		"id" => "online_location",
		"title" => $lang->online_location_manage,
		"link" => "index.php?module=rpgstuff-online_location"
	];
}

// Online Locations verwalten in ACP
function online_location_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache;

    if ($page->active_action != 'online_location') {
		return false;
	}

	if ($run_module == 'rpgstuff' && $action_file == 'online_location') {

        $lang->load('online_location');

        // Add to page navigation
        $page->add_breadcrumb_item($lang->online_location_manage, "index.php?module=rpgstuff-online_location");

		// ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

			// Optionen im Header bilden
			$page->output_header($lang->online_location_manage_header." - ".$lang->online_location_manage_overview);

			// Übersichtsseite Button
			$sub_tabs['online_location'] = [
				"title" => $lang->online_location_manage_overview,
				"link" => "index.php?module=rpgstuff-online_location",
				"description" => $lang->online_location_manage_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['online_location_add'] = [
				"title" => $lang->online_location_manage_add,
				"link" => "index.php?module=rpgstuff-online_location&amp;action=add",
				"description" => $lang->online_location_manage_add_desc
			];

			$page->output_nav_tabs($sub_tabs, 'online_location');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			// Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-online_location", "post", "", 1);
			$form_container = new FormContainer($lang->online_location_manage_overview);
			// Name/Identifikator
			$form_container->output_row_header($lang->online_location_manage_overview_identification, array('style' => 'text-align: left; width: 25%;'));
			// Seite/PHP Datei
			$form_container->output_row_header($lang->online_location_manage_overview_phpfile, array('style' => 'text-align: left; width: 20%;'));
			// Parameter
			$form_container->output_row_header($lang->online_location_manage_overview_parameter, array('style' => 'text-align: left; width: 15%;'));
			// Wert
			$form_container->output_row_header($lang->online_location_manage_overview_value, array('style' => 'text-align: left; width: 15%;'));
			// Optionen
			$form_container->output_row_header($lang->online_location_manage_overview_options, array('style' => 'text-align: center; width: 10%;'));
	
            // Alle Online Locations
			$query_elements = $db->query("SELECT * FROM ".TABLE_PREFIX."online_locations
            ORDER BY identification ASC
            ");

			while ($elements = $db->fetch_array($query_elements)) {

                $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-online_location&amp;action=edit&amp;olid='.$elements['olid'].'">'.htmlspecialchars_uni($elements['identification']).'</a></strong>');
                $form_container->output_cell(htmlspecialchars_uni($elements['phpfile']));

                // Kein Paramater => Hauptverzeichnis
                if (empty($elements['parameter'])) {
                    $form_container->output_cell("-");
                } else {
                    $form_container->output_cell(htmlspecialchars_uni($elements['parameter']));
                }

                // Kein Wert/Seitenbezeichnung => Hauptverzeichnis
                if (empty($elements['value'])) {
                    $form_container->output_cell("-");
                } else {
                    $form_container->output_cell(htmlspecialchars_uni($elements['value']));
                }

                // OPTIONEN
				$popup = new PopupMenu("online_location_".$elements['olid'], $lang->online_location_manage_overview_options);	
                $popup->add_item(
                    $lang->online_location_manage_overview_options_edit,
                    "index.php?module=rpgstuff-online_location&amp;action=edit&amp;olid=".$elements['olid']
                );
                $popup->add_item(
                    $lang->online_location_manage_overview_options_delete,
                    "index.php?module=rpgstuff-online_location&amp;action=delete&amp;olid=".$elements['olid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->online_location_manage_overview_delete_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();

            }

			// keine Online Locations bisher vorhanden
			if($db->num_rows($query_elements) == 0){
                $form_container->output_cell($lang->online_location_manage_no_elements, array("colspan" => 5));
                $form_container->construct_row();
			}

            $form_container->end();
            $form->end();
            $page->output_footer();
			exit;
        }

        // ONLINE LOCATION HINZUFÜGEN
        if ($mybb->get_input('action') == "add") {
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->online_location_manage_add_error_identification;
                }
                if (empty($mybb->get_input('phpfile'))) {
                    $errors[] = $lang->online_location_manage_add_error_phpfile;
                }
                if (empty($mybb->get_input('location_name'))) {
                    $errors[] = $lang->online_location_manage_add_error_location_name;
                }
                if (empty($mybb->get_input('parameter')) AND !empty($mybb->get_input('value'))) {
                    $errors[] = $lang->online_location_manage_add_error_value_parameter;
                }
    
                // No errors - insert
                if (empty($errors)) {
    
                    // Daten speichern
                    $new_location = array(
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "phpfile" => $db->escape_string($mybb->get_input('phpfile')),
                        "parameter" => $db->escape_string($mybb->get_input('parameter')),
                        "value" => $db->escape_string($mybb->get_input('value')),
                        "location_name" => $db->escape_string($mybb->get_input('location_name')),
                    );                    
                    
                    $db->insert_query("online_locations", $new_location);

                    $mybb->input['module'] = "Online Locations";
                    $mybb->input['action'] = $lang->online_location_manage_add_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['identification']));
    
                    flash_message($lang->online_location_manage_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-online_location");
                }
            }
    
            $page->add_breadcrumb_item($lang->online_location_manage_add);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->online_location_manage_header." - ".$lang->online_location_manage_add);
    
            // Übersichtsseite Button
			$sub_tabs['online_location'] = [
				"title" => $lang->online_location_manage_overview,
				"link" => "index.php?module=rpgstuff-online_location",
				"description" => $lang->online_location_manage_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['online_location_add'] = [
				"title" => $lang->online_location_manage_add,
				"link" => "index.php?module=rpgstuff-online_location&amp;action=add",
				"description" => $lang->online_location_manage_add_desc
			];
    
            $page->output_nav_tabs($sub_tabs, 'online_location_add');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            } 
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-online_location&amp;action=add", "post", "", 1);
            $form_container = new FormContainer($lang->online_location_manage_add);

            // Beispiel
            $form_container->output_row(
                $lang->online_location_manage_add_example,
                $lang->online_location_manage_add_example_desc
            );
    
            // Name
            $form_container->output_row(
                $lang->online_location_manage_add_identification,
                $lang->online_location_manage_add_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );
    
            // PHP Datei
            $form_container->output_row(
                $lang->online_location_manage_add_phpfile,
                $lang->online_location_manage_add_phpfile_desc,
                $form->generate_text_box('phpfile', $mybb->get_input('phpfile'))
            );
    
            // Parameter
            $form_container->output_row(
                $lang->online_location_manage_add_parameter,
                $lang->online_location_manage_add_parameter_desc,
                $form->generate_text_box('parameter', $mybb->get_input('parameter'))
            );
    
            // Bezeichnung der Seite
            $form_container->output_row(
                $lang->online_location_manage_add_value,
                $lang->online_location_manage_add_value_desc,
                $form->generate_text_box('value', $mybb->get_input('value'))
            );
    
            // Anzeige Wer ist Wo 
            $form_container->output_row(
                $lang->online_location_manage_add_location_name,
                $lang->online_location_manage_add_location_name_desc,
                $form->generate_text_area('location_name', $mybb->get_input('location_name'))
            );
    
            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->online_location_manage_add_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            
            $page->output_footer();
            exit;
        }

        // ONLINE LOCATION BEARBEITEN
        if ($mybb->get_input('action') == "edit") {
            
            // Get the data
            $olid = $mybb->get_input('olid', MyBB::INPUT_INT);
            $element_query = $db->simple_select("online_locations", "*", "olid = '".$olid."'");
            $element = $db->fetch_array($element_query);
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->online_location_manage_add_error_identification;
                }
                if (empty($mybb->get_input('phpfile'))) {
                    $errors[] = $lang->online_location_manage_add_error_phpfile;
                }
                if (empty($mybb->get_input('location_name'))) {
                    $errors[] = $lang->online_location_manage_add_error_location_name;
                }
                if (empty($mybb->get_input('parameter')) AND !empty($mybb->get_input('value'))) {
                    $errors[] = $lang->online_location_manage_add_error_value_parameter;
                }
                
                // No errors - insert
                if (empty($errors)) {
    
                    $olid = $mybb->get_input('olid', MyBB::INPUT_INT);
    
                    $update_location = array(
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "phpfile" => $db->escape_string($mybb->get_input('phpfile')),
                        "parameter" => $db->escape_string($mybb->get_input('parameter')),
                        "value" => $db->escape_string($mybb->get_input('value')),
                        "location_name" => $db->escape_string($mybb->get_input('location_name')),
                    ); 
    
                    $db->update_query("online_locations", $update_location, "olid='".$olid."'");
    
                    $mybb->input['module'] = "Online Locations";
                    $mybb->input['action'] = $lang->online_location_manage_edit_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->online_location_manage_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-online_location");
                }
            }
    
            $page->add_breadcrumb_item($lang->online_location_manage_edit);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->online_location_manage_header." - ".$lang->online_location_manage_edit);
    
            // Übersichtsseite Button
            $sub_tabs['online_location_edit'] = [
                "title" => $lang->online_location_manage_edit,
                "link" => "index.php?module=rpgstuff-online_location&amp;action=edit&olid=".$olid,
                "description" => $lang->online_location_manage_edit_desc
            ];
    
            $page->output_nav_tabs($sub_tabs, 'online_location_edit');

            // Seite bilden
            if(!empty($element['parameter']) AND !empty($element['parameter'])) {
                $full_url = $element['phpfile'].".php?".$element['parameter']."=".$element['value'];
            } else {
                $full_url = $element['phpfile'].".php";
            }
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
                $element['identification'] = $mybb->input['identification'];
                $element['phpfile'] = $mybb->input['phpfile'];
                $element['parameter'] = $mybb->input['parameter'];
                $element['value'] = $mybb->input['value'];
                $element['location_name'] = $mybb->input['location_name'];
            }
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-online_location&amp;action=edit", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->online_location_manage_edit_container, $full_url));
            echo $form->generate_hidden_field('olid', $olid);
    
            // Name
            $form_container->output_row(
                $lang->online_location_manage_add_identification,
                $lang->online_location_manage_add_identification_desc,
                $form->generate_text_box('identification', $element['identification'])
            );
    
            // PHP Datei
            $form_container->output_row(
                $lang->online_location_manage_add_phpfile,
                $lang->online_location_manage_add_phpfile_desc,
                $form->generate_text_box('phpfile', $element['phpfile'])
            );
    
            // Parameter
            $form_container->output_row(
                $lang->online_location_manage_add_parameter,
                $lang->online_location_manage_add_parameter_desc,
                $form->generate_text_box('parameter', $element['parameter'])
            );
    
            // Bezeichnung der Seite
            $form_container->output_row(
                $lang->online_location_manage_add_value,
                $lang->online_location_manage_add_value_desc,
                $form->generate_text_box('value', $element['value'])
            );
    
            // Anzeige Wer ist Wo 
            $form_container->output_row(
                $lang->online_location_manage_add_location_name,
                $lang->online_location_manage_add_location_name_desc,
                $form->generate_text_area('location_name', $element['location_name'])
            );
    
            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->online_location_manage_edit_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            $page->output_footer();
            exit;
        }

        // ONLINE LOCATION LÖSCHEN
		if ($mybb->input['action'] == "delete") {

			// Get data
			$olid = $mybb->get_input('olid', MyBB::INPUT_INT);
			$query = $db->simple_select("online_locations", "*", "olid='".$olid."'");
			$del_type = $db->fetch_array($query);

			// Error Handling
			if (empty($olid)) {
				flash_message($lang->online_location_manage_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-online_location");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-online_location");
			}

			if ($mybb->request_method == "post") {

                // Element aus der DB löschen
				$db->delete_query("online_locations", "olid = '".$olid."'");	

				$mybb->input['module'] = "Online Locations";
				$mybb->input['action'] = $lang->online_location_manage_overview_delete_logadmin;
				log_admin_action(htmlspecialchars_uni($del_type['identification']));

				flash_message($lang->online_location_manage_overview_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-online_location");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-online_location&amp;action=delete&amp;olid=".$olid,
					$lang->online_location_manage_overview_delete_notice
				);
			}
			exit;
		}
    }
}

// Plugin Update
function online_location_admin_update_plugin(&$table) {

    global $db, $mybb, $lang, $cache;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "online_location") {

        // Datenbanktabellen & Felder
        online_location_database();

        // Collation prüfen und korrigieren
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';

        $collation_string = $db->build_create_table_collation();
        if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
            $charset = $matches[1];
            $collation = $matches[2];
        }

        $databaseTables = [
            "online_locations"
        ];

        foreach ($databaseTables as $databaseTable) {
            if ($db->table_exists($databaseTable)) {
                $table = TABLE_PREFIX.$databaseTable;

                $query = $db->query("SHOW TABLE STATUS LIKE '".$db->escape_string($table)."'");
                $table_status = $db->fetch_array($query);
                $actual_collation = strtolower($table_status['Collation'] ?? '');

                if (!empty($collation) && $actual_collation !== strtolower($collation)) {
                    $db->query("ALTER TABLE {$table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}");
                }
            }
        }

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Online Locations Erweiterung")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = online_location_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=online_location\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// ONLINE ANZEIGE - WER IST WO
function online_location_wol_activity($user_activity) {

	global $user, $db, $parameter, $value;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) {
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

    $olid = "";
    // Unterseite
    if (!empty($split_loc[1])) {
        // Name der Unterseite
        $split_value = explode("=", $split_loc[1]);
        // Parameter
        $value = $split_value[1];

        // Name des Actions
        $split_parameter = explode("?", $split_value[0]);
        // Actiom
        $parameter = $split_parameter[1];


        // Zählen, ob der Name der Unterseite vorhanden ist
        $count_para = $db->num_rows($db->query("SELECT olid FROM ".TABLE_PREFIX."online_locations
        WHERE value = '".$value."'
        "));

        // Vorhanden - fester Parameter
        if ($count_para != 0) {
            $olid = $db->fetch_field($db->simple_select("online_locations", "olid", "value = '".$value."'"), "olid");
        } 
        // flexibler Parameter - UID zB
        else {
            $olid = $db->fetch_field($db->simple_select("online_locations", "olid", "parameter = '".$parameter."' AND value = ''"), "olid");
        }
        
    } 
    // HAUPTSEITE
    else {
        $php_file = $filename;
        
        $olid = $db->fetch_field($db->simple_select("online_locations", "olid", "phpfile = '".$php_file."' AND parameter = ''  AND value = ''"), "olid");
    }

    $phpfile = $db->fetch_field($db->simple_select("online_locations", "phpfile", "olid = '".$olid."'"), "phpfile");

    switch ($filename) {
        case $phpfile:
            $user_activity['activity'] = "onlinelocation_".$olid;
        break;
    }

	return $user_activity;
}
function online_location_wol_location($plugin_array) {

    global $db;
	
    $split_loc = explode("_", $plugin_array['user_activity']['activity']);
    
    if (isset($split_loc[1])) {
        $olid = $split_loc[1];

        $location_text = $db->fetch_field($db->simple_select("online_locations", "location_name", "olid = '".$olid."'"), "location_name");

        if ($plugin_array['user_activity']['activity'] == "onlinelocation_".$olid) {
            $plugin_array['location_name'] = $location_text;
        }
    }

    return $plugin_array;
} 

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// DATENBANKTABELLE
function online_location_database() {

    global $db;

    if (!$db->table_exists("online_locations")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."online_locations(
            `olid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `identification` VARCHAR(500) COLLATE utf8_general_ci  NOT NULL,
            `phpfile` VARCHAR(500) COLLATE utf8_general_ci NOT NULL,
            `parameter` VARCHAR(500) COLLATE utf8_general_ci NOT NULL,
            `value` VARCHAR(500) COLLATE utf8_general_ci NOT NULL,
            `location_name` VARCHAR(500) COLLATE utf8_general_ci NOT NULL,
            PRIMARY KEY(`olid`),
            KEY `olid` (`olid`)
            )
            ENGINE=InnoDB ".$db->build_create_table_collation().";    
        ");
    }
}

// UPDATE CHECK
function online_location_is_updated() {
    
    global $db;

    $charset = 'utf8mb4';
    $collation = 'utf8mb4_unicode_ci';

    $collation_string = $db->build_create_table_collation();
    if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
        $charset = strtolower($matches[1]);
        $collation = strtolower($matches[2]);
    }

    $databaseTables = [
        "online_locations"
    ];

    foreach ($databaseTables as $table_name) {
        if (!$db->table_exists($table_name)) {
            return false;
        }

        $full_table_name = TABLE_PREFIX . $table_name;

        $query = $db->query("
            SELECT TABLE_COLLATION 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$db->escape_string($full_table_name)."'
        ");
        $result = $db->fetch_array($query);
        $actual_collation = strtolower($result['TABLE_COLLATION'] ?? '');

        if ($actual_collation !== $collation) {
            return false;
        }
    }

    return true;
}

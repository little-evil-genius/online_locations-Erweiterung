<?php
//error_reporting ( -1 );
//ini_set ( 'display_errors', true );
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Set cache header
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// HOOKS
$plugins->add_hook('admin_config_settings_change', 'uploadsystem_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'uploadsystem_settings_peek');
$plugins->add_hook("admin_tools_action_handler", "uploadsystem_admin_tools_action_handler");
$plugins->add_hook("admin_tools_permissions", "uploadsystem_admin_tools_permissions");
$plugins->add_hook("admin_tools_menu", "uploadsystem_admin_tools_menu");
$plugins->add_hook("admin_load", "uploadsystem_admin_manage");
$plugins->add_hook("datahandler_user_insert_end", "uploadsystem_user_insert");
$plugins->add_hook("admin_user_users_delete_commit_end", "uploadsystem_user_delete");
$plugins->add_hook('usercp_menu', 'uploadsystem_nav', 40);
$plugins->add_hook('usercp_start', 'uploadsystem_usercp');
$plugins->add_hook("fetch_wol_activity_end", "uploadsystem_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "uploadsystem_online_location");
$plugins->add_hook("postbit", "uploadsystem_postbit", 0);
$plugins->add_hook("memberlist_user", "uploadsystem_memberlist", 0);
$plugins->add_hook("member_profile_end", "uploadsystem_memberprofile", 0);
$plugins->add_hook("global_start", "uploadsystem_global");
$plugins->add_hook("usercp_do_editsig_start", "uploadsystem_uploadsig");
$plugins->add_hook("usercp_editsig_end", "uploadsystem_editsig"); 
 
// Die Informationen, die im Pluginmanager angezeigt werden
function uploadsystem_info(){
	return array(
		"name"		=> "Upload-System",
		"description"	=> "Dieses Plugin ermöglicht den Usern verschiedene Grafiken per internem Upload-System hochzuladen. Auch kann eine Signatur-Datei hochgeladen werden.",
		"website"	=> "https://github.com/little-evil-genius",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function uploadsystem_install(){
    
    global $db, $cache, $mybb;

    // DATENBANKEN ERSTELLEN
    // Upload Möglichkeiten
    $db->query("CREATE TABLE ".TABLE_PREFIX."uploadsystem(
        `usid` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `disporder` int(10) default '0',
		`identification` VARCHAR(250) COLLATE utf8_general_ci  NOT NULL,
        `name` VARCHAR(250) COLLATE utf8_general_ci NOT NULL,
        `description` VARCHAR(500) COLLATE utf8_general_ci NOT NULL,
        `path` text COLLATE utf8_general_ci NOT NULL,
        `allowextensions` VARCHAR(100) COLLATE utf8_general_ci NOT NULL,
        `mindims` VARCHAR(100) COLLATE utf8_general_ci NOT NULL,
        `maxdims` VARCHAR(100) COLLATE utf8_general_ci NOT NULL default '',
        `square` int(1) unsigned NOT NULL default '0',
        `bytesize` VARCHAR(100) COLLATE utf8_general_ci NOT NULL default '5120',
        PRIMARY KEY(`usid`),
        KEY `usid` (`usid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    "
    );

    // einzelne Datein
    $db->query("CREATE TABLE ".TABLE_PREFIX."uploadfiles(
        `ufid` int(10) unsigned NOT NULL default '0',
        `signatur` TEXT COLLATE utf8_general_ci NOT NULL,
        PRIMARY KEY(`ufid`),
        KEY `ufid` (`ufid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    "
    );

    // HAUPTVERZEICHNIS ERSTELLEN
    if (!is_dir(MYBB_ROOT.'uploads/uploadsystem')) {
        mkdir(MYBB_ROOT.'uploads/uploadsystem', 0777, true);
    }

    // SIGNATUR VERZEICHNIS ERSTELLEN
    if (!is_dir(MYBB_ROOT.'uploads/uploadsystem/signatur') AND is_dir(MYBB_ROOT.'uploads/uploadsystem')) {
        mkdir(MYBB_ROOT.'uploads/uploadsystem/signatur', 0777, true);
    }

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'uploadsystem',
        'title'         => 'Uploadsystem',
        'description'   => 'Einstellungen für das interne Uploadsystem',
        'disporder'     => $maxdisporder,
        'isdefault'     => 0
    );
        
    $gid = $db->insert_query("settinggroups", $setting_group); 
        
    $setting_array = array(
		'uploadsystem_allowed_extensions' => array(
			'title' => 'Erlaubte Dateitypen',
			'description' => 'Welche Dateitypen dürfen allgemein über das Upload-System hochgeladen werden?',
			'optionscode' => 'text',
			'value' => 'png, jpg, jpeg, gif, bmp', // Default
			'disporder' => 1
		),
		'uploadsystem_signatur' => array(
			'title' => 'Signaturen hochladen',
			'description' => 'Dürfen User auch ihre Signaturen über das Upload-System hochladen?',
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 2
		),
        'uploadsystem_signatur_max' => array(
            'title' => 'maximale Signaturgröße',
            'description' => "Wie groß dürfen Signaturen maximal sein? Breite und Höhe getrennt durch x oder |. Wenn das Feld leer bleibt, wird die Größe nicht beschränkt.",
            'optionscode' => 'text',
            'value' => '500x250', // Default
            'disporder' => 3
        ),
        'uploadsystem_signatur_size' => array(
            'title' => 'Maximale Datei-Größe',
            'description' => 'Die maximale Dateigröße (in Kilobyte) für hochgeladene Signaturen beträgt (0 = Keine Beschränkung)? Der Defaultwert beträgt 5 MB.<br>Gewünschte MBx1024 = KB Wert. 5x1024 = 5120',
            'optionscode' => 'text',
            'value' => '5120', // Default
            'disporder' => 4
        ),
        'uploadsystem_signatur_extensions' => array(
            'title' => 'Erlaubte Dateitypen für Signaturen',
            'description' => 'Welche Dateitypen dürfen für die Signaturen hochgeladen werden?',
            'optionscode' => 'text',
            'value' => 'png, jpg, jpeg', // Default
            'disporder' => 5
        ),
    );
        
    foreach($setting_array as $name => $setting){
        $setting['name'] = $name;
        $setting['gid']  = $gid;
        $db->insert_query('settings', $setting);  
    }
    rebuild_settings();


	// TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "uploadsystem",
        "title" => $db->escape_string("Uploadsystem"),
    );

    $db->insert_query("templategroups", $templategroup);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp',
        'template'	=> $db->escape_string('<html>
        <head>
            <title>{$lang->user_cp} - {$lang->uploadsystem_usercp}</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table width="100%" border="0" align="center">
                <tr>
                    {$usercpnav}
                    <td valign="top">
                        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                            <tr>
                                <td class="thead">
                                    <strong>{$lang->uploadsystem_usercp}</strong>
                                </td>
                            </tr>
                            {$uploadsystem_error}
                            <tr>
                                <td>
                                    <div class="uploadsystem-desc">{$lang->uploadsystem_usercp_desc}</div>
                                    {$upload_element}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
     </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_element',
        'template'	=> $db->escape_string('<div class="uploadsystem_element">
        <div class="uploadsystem_element_headline"><strong>{$headline}</strong></div>
        <div class="uploadsystem_element_main">
            <div class="uploadsystem_element_info">
                {$description}</br></br>
        {$dims} {$square}<br>
        {$extensions}<br>
        {$size}<br><br>
        {$element_notice}
        </div>
        <div>
        <div class="uploadsystem_element_preview" style="background:url(\'$file_url\');background-size: cover;width:{$minwidth}px;height:{$minheight}px;">
            {$graphic_size}
        </div>
        </div>
        </div>
        {$upload}
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_element_remove',
        'template'	=> $db->escape_string('<input type="submit" value="{$lang->uploadsystem_usercp_element_button_remove}" name="remove_upload" class="button">'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_element_upload',
        'template'	=> $db->escape_string('<form method="post" action="usercp.php?action=uploadsystem" enctype="multipart/form-data">
        <div class="uploadsystem_upload">
            <div class="uploadsystem_upload_info">
                <b>{$headline_upload}</b></br>
            {$subline_upload}
        </div>
        <div class="uploadsystem_upload_input">
            <input type="file" name="pic_{$identification}">
        </div>
        <div class="uploadsystem_upload_button">                            
            <input type="hidden" name="usID" id="usID" value="{$usid}" />
            <input type="hidden" name="action" value="do_upload">         
            <input type="submit" value="{$lang->uploadsystem_usercp_element_button}" name="new_upload" class="button">
            {$remove}
        </div>
        </div>
        </form>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_nav',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1 smalltext">
            <a href="usercp.php?action=uploadsystem" class="usercp_nav_item usercp_nav_subscriptions">{$lang->uploadsystem_usercp_nav}</a>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_signatur',
        'template'	=> $db->escape_string('<tr>
        <td class="trow2">
            <b>{$lang->uploadsystem_usercp_signatur_headline}</b>
        </td>
        <td class="trow2">
            <div class="uploadsystem_signatur">
                <div class="uploadsystem_signatur_info">
                    <b>{$lang->uploadsystem_usercp_signatur_link_headline}</b></br>
                <span class="smalltext">{$file_url}</span>
            </div>
            <div class="uploadsystem_signatur_input">
                <input type="file" name="signaturlink"><br>
                <span class="smalltext">{$element_notice}</span>
            </div>
            <div class="uploadsystem_signatur_button"> 
                <input type="hidden" name="action" value="do_editsig" />                    
                <input type="submit" class="button" name="new_signatur" value="{$uploadsystem_usercp_signatur_button}" />
                {$remove}
            </div>
            </div>
            </td>
            </tr>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'uploadsystem_usercp_signatur_remove',
        'template'	=> $db->escape_string('<input type="submit" value="{$lang->uploadsystem_usercp_signatur_button_remove}" name="remove_signatur" class="button">'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // STYLESHEET HINZUFÜGEN
    $css = array(
        'name' => 'uploadsystem.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" => '.uploadsystem-desc {
            text-align: justify;
            line-height: 180%;
            padding: 20px 40px;
        }
        
        .uploadsystem_element {
            margin-bottom: 10px;
        }
        
        .uploadsystem_element:last-child {
            margin-bottom: 0;
        }
        
        .uploadsystem_element_headline {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        
        .uploadsystem_element_main {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px;
        }
        
        .uploadsystem_element_info {
            text-align: justify;
        }
        
        .uploadsystem_element_preview {
            background-size: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #6f6d6d;
            font-weight: bold;
        }
        
        .uploadsystem_upload {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 0 10px;
            align-items: center;
        }
        
        .uploadsystem_upload_info {
            width: 45%;
            border-right: 1px solid;
            border-color: #ddd;
        }
        
        .uploadsystem_upload_input {
            width: 53%;
        }
        
        .uploadsystem_upload_button {
            width: 100%;
            text-align: center;
        }
        
        .uploadsystem_signatur {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            padding: 0 10px;
            align-items: center;
        }
        
        .uploadsystem_signatur_info {
            width: 54%;
            border-right: 1px solid;
            border-color: #ddd;
        }
        
        .uploadsystem_signatur_input {
            width: 44%;
            padding-left: 10px;
        }
        
        .uploadsystem_signatur_button {
            width: 100%;
            text-align: center;
            margin-top: 10px;
        }',
        'cachefile' => $db->escape_string(str_replace('/', '', 'uploadsystem.css')),
        'lastmodified' => time()
    );
    
    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "uploadsystem.css"), "sid = '".$sid."'", 1);

    $tids = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }

}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function uploadsystem_is_installed(){

    global $db, $mybb;

    if ($db->table_exists("uploadfiles")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function uploadsystem_uninstall(){
    
	global $db;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("uploadsystem"))
    {
        $db->drop_table("uploadsystem");
    }
    if($db->table_exists("uploadfiles"))
    {
        $db->drop_table("uploadfiles");
    }
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'uploadsystem%'");
    $db->delete_query('settinggroups', "name = 'uploadsystem'");

    rebuild_settings();

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'uploadsystem'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'uploadsystem%'");

    // VERZEICHNIS LÖSCHEN
    rmdir(MYBB_ROOT.'uploads/uploadsystem');

	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // STYLESHEET ENTFERNEN
	$db->delete_query("themestylesheets", "name = 'uploadsystem.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function uploadsystem_activate() {
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    
    find_replace_templatesets('usercp_editsig', '#'.preg_quote('<form action="usercp.php" method="post">').'#', '<form action="usercp.php" method="post" enctype="multipart/form-data">');
    find_replace_templatesets('usercp_editsig', '#'.preg_quote('<tr><td class="trow2"><span class="smalltext">{$lang->edit_sig_note2}</span></td>').'#', '{$upload_signatur} <tr><td class="trow2"><span class="smalltext">{$lang->edit_sig_note2}</span></td>');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function uploadsystem_deactivate() {
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    find_replace_templatesets("usercp_editsig", "#".preg_quote(' enctype="multipart/form-data"')."#i", '', 0);
    find_replace_templatesets("usercp_editsig", "#".preg_quote('{$upload_signatur}')."#i", '', 0);
}

#####################################
### THE BIG MAGIC - THE FUNCTIONS ###
#####################################

// ADMIN-CP PEEKER
function uploadsystem_settings_change(){
    
    global $db, $mybb, $uploadsystem_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='uploadsystem'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $uploadsystem_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}
function uploadsystem_settings_peek(&$peekers){

    global $mybb, $uploadsystem_settings_peeker;

	if ($uploadsystem_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_uploadsystem_signatur"), $("#row_setting_uploadsystem_signatur_max, #row_setting_uploadsystem_signatur_size, #row_setting_uploadsystem_signatur_extensions"),/1/,true)';
    }
}

// ADMIN BEREICH - KONFIGURATION //
// action handler fürs acp konfigurieren
function uploadsystem_admin_tools_action_handler(&$actions) {
	$actions['uploadsystem'] = array('active' => 'uploadsystem', 'file' => 'uploadsystem');
}

// Berechtigungen im ACP - Adminrechte
function uploadsystem_admin_tools_permissions(&$admin_permissions) {
	global $lang;
	
    $lang->load('uploadsystem');

	$admin_permissions['uploadsystem'] = $lang->uploadsystem_permission;

	return $admin_permissions;
}

// Menü einfügen
function uploadsystem_admin_tools_menu(&$sub_menu) {
	global $mybb, $lang;
	
    $lang->load('uploadsystem');

	$sub_menu[] = [
		"id" => "uploadsystem",
		"title" => $lang->uploadsystem_manage,
		"link" => "index.php?module=tools-uploadsystem"
	];
}

// Uploadsystem verwalten in ACP
function uploadsystem_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache;

    require_once MYBB_ROOT."inc/functions_upload.php";
    require_once MYBB_ROOT."inc/functions.php";

	$lang->load('uploadsystem');

    // EINSTELLUNGEN
    $allowed_extensions = $mybb->settings['uploadsystem_allowed_extensions'];
    $extensions_string = str_replace(", ", ",", $allowed_extensions);
    $extensions_values = explode (",", $extensions_string);

    if ($page->active_action != 'uploadsystem') {
		return false;
	}

	// Add to page navigation
	$page->add_breadcrumb_item($lang->uploadsystem_manage, "index.php?module=tools-uploadsystem");

	if ($run_module == 'tools' && $action_file == 'uploadsystem') {

		// ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

			// Optionen im Header bilden
			$page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_overview);

			// Übersichtsseite Button
			$sub_tabs['uploadsystem'] = [
				"title" => $lang->uploadsystem_manage_overview,
				"link" => "index.php?module=tools-uploadsystem",
				"description" => $lang->uploadsystem_manage_overview_desc
			];
			// Upload Hinzufüge Button
			$sub_tabs['uploadsystem_upload_add'] = [
				"title" => $lang->uploadsystem_manage_add_upload,
				"link" => "index.php?module=tools-uploadsystem&amp;action=add_upload",
				"description" => $lang->uploadsystem_manage_add_upload_desc
			];
			// Userdatein verwalten Button
			$sub_tabs['uploadsystem_userfiles'] = [
				"title" => $lang->uploadsystem_manage_userfiles,
				"link" => "index.php?module=tools-uploadsystem&amp;action=userfiles",
				"description" => $lang->uploadsystem_manage_userfiles_desc
			];
			// User in DB Button
			$sub_tabs['uploadsystem_usercheck'] = [
				"title" => $lang->uploadsystem_manage_usercheck,
				"link" => "index.php?module=tools-uploadsystem&amp;action=usercheck",
				"description" => $lang->uploadsystem_manage_usercheck_desc
			];

			$page->output_nav_tabs($sub_tabs, 'uploadsystem');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			// Übersichtsseite
			$form = new Form("index.php?module=tools-uploadsystem", "post", "", 1);
			$form_container = new FormContainer($lang->uploadsystem_manage_overview);
			// Name
			$form_container->output_row_header($lang->uploadsystem_manage_overview_name, array('style' => 'text-align: left; width: 25%;'));
			// Pfad
			$form_container->output_row_header($lang->uploadsystem_manage_overview_path, array('style' => 'text-align: left; width: 20%;'));
			// Erlaubte Dateitypen
			$form_container->output_row_header($lang->uploadsystem_manage_overview_extensions, array('style' => 'text-align: left; width: 15%;'));
			// Größe
			$form_container->output_row_header($lang->uploadsystem_manage_overview_dims, array('style' => 'text-align: left; width: 15%;'));
			// Quadrat
			$form_container->output_row_header($lang->uploadsystem_manage_overview_square, array('style' => 'text-align: center; width: 5%;'));
			// max. Dateigröße
			$form_container->output_row_header($lang->uploadsystem_manage_overview_size, array('style' => 'text-align: center; width: 10%;'));
			// Optionen
			$form_container->output_row_header($lang->uploadsystem_manage_overview_options, array('style' => 'text-align: center; width: 10%;'));
	
            // Alle Elemente
			$query_elements = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadsystem
            ORDER BY disporder ASC, name ASC
            ");

			while ($elements = $db->fetch_array($query_elements)) {

                $form_container->output_cell('<strong><a href="index.php?module=tools-uploadsystem&amp;action=edit_element&amp;usid='.$elements['usid'].'">'.htmlspecialchars_uni($elements['name']).'</a></strong><br><small>'.htmlspecialchars_uni($elements['description']).'</small>');
                $form_container->output_cell(htmlspecialchars_uni($elements['path']));
                $form_container->output_cell(htmlspecialchars_uni($elements['allowextensions']));

                if (empty($elements['maxdims'])) {
                    $form_container->output_cell('<justify>'.$lang->sprintf($lang->uploadsystem_manage_overview_dims_min, $elements['mindims']).'</justify>');
                } else {
                    if ($elements['maxdims'] != $elements['mindims']) {
                        $form_container->output_cell('<justify>'.$lang->sprintf($lang->uploadsystem_manage_overview_dims_minmax, $elements['mindims'], $elements['maxdims']).'</justify>');
                    } else {
                        $form_container->output_cell('<justify>'.$lang->sprintf($lang->uploadsystem_manage_overview_dims_fix, $elements['mindims']).'</justify>');
                    }
                }

                if ($elements['square'] == 1) {
                    $form_container->output_cell('<center>'.$lang->uploadsystem_manage_overview_square_yes.'</center>');
                } else {
                    $form_container->output_cell('<center>'.$lang->uploadsystem_manage_overview_square_no.'</center>');
                }

                $form_container->output_cell('<center>'.get_friendly_size($elements['bytesize']*1024).'</center>');

                // OPTIONEN
				$popup = new PopupMenu("uploadsystem_".$elements['usid'], $lang->uploadsystem_manage_overview_options);	
                $popup->add_item(
                    $lang->uploadsystem_manage_overview_options_edit,
                    "index.php?module=tools-uploadsystem&amp;action=edit_element&amp;usid=".$elements['usid']
                );
                $popup->add_item(
                    $lang->uploadsystem_manage_overview_options_delete,
                    "index.php?module=tools-uploadsystem&amp;action=delete_element&amp;usid=".$elements['usid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->uploadsystem_manage_overview_delete_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();
            }

			// keine Elemente bisher vorhanden
			if($db->num_rows($query_elements) == 0){
                $form_container->output_cell($lang->uploadsystem_manage_no_elements, array("colspan" => 7));
                $form_container->construct_row();
			}

            $form_container->end();
            $form->end();
            $page->output_footer();
			exit;
        }

        // ELEMENT HINZUFÜGEN
        if ($mybb->get_input('action') == "add_upload") {
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_identification;
                }
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_name;
                }
                if (empty($mybb->get_input('description'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_description;
                }
    
                // Dateiformate
                $checkbox_inputs = "";
                foreach($extensions_values as $value){
                    if ($mybb->get_input($value) != "") {
                        $checkbox_inputs .= $mybb->get_input($value).", ";
                    } else {
                        $checkbox_inputs .= "";
                    }	
                }
    
                if (empty($checkbox_inputs)) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_extensions;
                }
                if (empty($mybb->get_input('mindims'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_mindims;
                }
    
                // No errors - insert
                if (empty($errors)) {
    
                    // Komma entfernen
                    $checkbox_inputs = substr($checkbox_inputs, 0, -2);

                    // Ordner Pfad
                    $folder_path =  "uploads/uploadsystem/".$mybb->get_input('identification')."/"; 
    
                    // Daten speichern
                    $new_uploadelement = array(
                        "disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "path" => $db->escape_string($folder_path),
                        "allowextensions" => $db->escape_string($checkbox_inputs),
                        "mindims" => $db->escape_string($mybb->get_input('mindims')),
                        "maxdims" => $db->escape_string($mybb->get_input('maxdims')),
                        "square" => $mybb->get_input('square', MyBB::INPUT_INT),
                        "bytesize" => $db->escape_string($mybb->get_input('bytesize')),
                    );                    
                    
                    $db->insert_query("uploadsystem", $new_uploadelement);
            
                    // VERZEICHNIS ERSTELLEN
                    if (!is_dir(MYBB_ROOT."uploads/uploadsystem/".$mybb->get_input('identification'))) {
                        mkdir(MYBB_ROOT."uploads/uploadsystem/".$mybb->get_input('identification'), 0777, true);
                    }

                    // NEUE DB SPALTE
                    $db->write_query("ALTER TABLE ".TABLE_PREFIX."uploadfiles ADD ".$db->escape_string($mybb->get_input('identification'))." TEXT NOT NULL");
    
                    $mybb->input['module'] = "Uploadsystem";
                    $mybb->input['action'] = $lang->uploadsystem_manage_add_upload_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->uploadsystem_manage_add_upload_flash, 'success');
                    admin_redirect("index.php?module=tools-uploadsystem");
                }
            }
    
            $page->add_breadcrumb_item($lang->uploadsystem_manage_add_upload);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1822"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1827"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1805"></script>
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
            $page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_add_upload);
    
            // Übersichtsseite Button
            $sub_tabs['uploadsystem'] = [
                "title" => $lang->uploadsystem_manage_overview,
                "link" => "index.php?module=tools-uploadsystem",
                "description" => $lang->uploadsystem_manage_overview_desc
            ];
            // Upload Hinzufüge Button
            $sub_tabs['uploadsystem_upload_add'] = [
                "title" => $lang->uploadsystem_manage_add_upload,
                "link" => "index.php?module=tools-uploadsystem&amp;action=add_upload",
                "description" => $lang->uploadsystem_manage_add_upload_desc
            ];
            // Userdatein verwalten Button
            $sub_tabs['uploadsystem_userfiles'] = [
                "title" => $lang->uploadsystem_manage_userfiles,
                "link" => "index.php?module=tools-uploadsystem&amp;action=userfiles",
                "description" => $lang->uploadsystem_manage_userfiles_desc
            ];
			// User in DB Button
			$sub_tabs['uploadsystem_usercheck'] = [
				"title" => $lang->uploadsystem_manage_usercheck,
				"link" => "index.php?module=tools-uploadsystem&amp;action=usercheck",
				"description" => $lang->uploadsystem_manage_usercheck_desc
			];
    
            $page->output_nav_tabs($sub_tabs, 'uploadsystem_upload_add');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            } else {
                $mybb->input['square'] = 0;
                $mybb->input['bytesize'] = 5120;
            }
    
            // Build the form
            $form = new Form("index.php?module=tools-uploadsystem&amp;action=add_upload", "post", "", 1);
            $form_container = new FormContainer($lang->uploadsystem_manage_add_upload);
    
            // Identifikator
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_identification,
                $lang->uploadsystem_manage_add_upload_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );
    
            // Name
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_name,
                $lang->uploadsystem_manage_add_upload_name_desc,
                $form->generate_text_box('name', $mybb->get_input('name'))
            );
    
            // Beschreibung
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_description,
                $lang->uploadsystem_manage_add_upload_description_desc,
                $form->generate_text_box('description', $mybb->get_input('description'))
            );
    
            // Sortierung
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_disporder,
                $lang->uploadsystem_manage_add_upload_disporder_desc,
                $form->generate_numeric_field('disporder', $mybb->get_input('description'), array('id' => 'disporder', 'min' => 0))
            );
    
            // erlaubte Dateitypen
            $checkbox_extensions = "";
            foreach($extensions_values as $value){
                $big_value = strtoupper($value);
                $checkbox_extensions .= $form->generate_check_box($value, $value, $lang->sprintf($lang->uploadsystem_manage_add_upload_extensions_value, $big_value), array('checked' => $mybb->get_input($value), 'id' => $value)).",";
            }
            $split_checkbox = explode(",", $checkbox_extensions);
            $checkbox_extensions = implode('<br />', $split_checkbox);
            
            $extensions_options = array(
                $checkbox_extensions
            );
            $form_container->output_row($lang->uploadsystem_manage_add_upload_extensions, $lang->uploadsystem_manage_add_upload_extensions_desc, implode('<br />', $extensions_options), '', array(), array('id' => 'row_extensions_options'));
    
            // Minimale Grafik-Größe
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_mindims,
                $lang->uploadsystem_manage_add_upload_mindims_desc,
                $form->generate_text_box('mindims', $mybb->get_input('mindims'))
            );
    
            // Maximale Grafik-Größe
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_maxdims,
                $lang->uploadsystem_manage_add_upload_maxdims_desc,
                $form->generate_text_box('maxdims', $mybb->get_input('maxdims'))
            );
    
            // Quadratisch
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_square,
                $lang->uploadsystem_manage_add_upload_square_desc,
                $form->generate_yes_no_radio('square', $mybb->get_input('square'))
            );
    
            // maximale Dateigröße
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_bytesize,
                $lang->uploadsystem_manage_add_upload_bytesize_desc,
                $form->generate_numeric_field('bytesize', $mybb->get_input('bytesize'), array('id' => 'bytesize', 'min' => 0))
            );
    
    
            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->uploadsystem_manage_add_upload_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            
            $page->output_footer();
            exit;
        }
    
        // ELEMENT BEARBEITEN
        if ($mybb->get_input('action') == "edit_element") {
            
            // Get the data
            $usid = $mybb->get_input('usid', MyBB::INPUT_INT);
            $element_query = $db->simple_select("uploadsystem", "*", "usid = '".$usid."'");
            $element = $db->fetch_array($element_query);
    
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_identification;
                }
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_name;
                }
                if (empty($mybb->get_input('description'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_description;
                }
    
                // Dateiformate
                $checkbox_inputs = "";
                foreach($extensions_values as $value){
                    if ($mybb->get_input($value) != "") {
                        $checkbox_inputs .= $mybb->get_input($value).", ";
                    } else {
                        $checkbox_inputs .= "";
                    }	
                }
    
                if (empty($checkbox_inputs)) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_extensions;
                }
                if (empty($mybb->get_input('mindims'))) {
                    $errors[] = $lang->uploadsystem_manage_add_upload_error_mindims;
                }
    
                // No errors - insert
                if (empty($errors)) {
    
                    $usid = $mybb->get_input('usid', MyBB::INPUT_INT);
    
                    // Komma entfernen
                    $checkbox_inputs = substr($checkbox_inputs, 0, -2);
    
                    // Umbennen, wenn nötig
                    if ($mybb->get_input('identification') != $element['identification']) {
                        
                        // Pfad ändern
                        $folder_path =  "uploads/uploadsystem/".$mybb->get_input('identification')."/"; 
                        
                        // Ordner Name ändern
                        rename(MYBB_ROOT."uploads/uploadsystem/".$element['identification'], MYBB_ROOT."uploads/uploadsystem/".$mybb->get_input('identification'));

                        // Spalte in der DB ändern
                        $db->write_query("ALTER TABLE ".TABLE_PREFIX."uploadfiles CHANGE `".$element['identification']."` `".$mybb->get_input('identification')."` TEXT NOT NULL");

                        // Alle Dateien ändern
                        $new_identification = $mybb->get_input('identification');

                        $allfiles_query = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadfiles uf
                        WHERE uf.".$new_identification." != ''
                        ");
                        $files_names = [];
                        while($allfiles = $db->fetch_array($allfiles_query)) {
                            $ufid = $allfiles['ufid'];
                            $files_names[$ufid] = $allfiles[$new_identification];
                        }
               
                        foreach ($files_names as $ufid => $filename) {
                            $new_name = str_replace($element['identification'], $new_identification, $filename);
                            $update_name = array(
                                $new_identification => $db->escape_string($new_name)
                            );
                            $db->update_query("uploadfiles", $update_name, "ufid='".$ufid."'");

                            // Datein korrekt unbennen
                            rename(MYBB_ROOT."uploads/uploadsystem/".$new_identification."/".$filename, MYBB_ROOT."uploads/uploadsystem/".$new_identification."/".$new_name);
                        }

                    } else {
                        $folder_path =  "uploads/uploadsystem/".$element['identification']."/"; 
                    }
    
                    $edit_uploadelement = array(
                        "disporder" => $mybb->get_input('disporder', MyBB::INPUT_INT),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "path" => $db->escape_string($folder_path),
                        "allowextensions" => $db->escape_string($checkbox_inputs),
                        "mindims" => $db->escape_string($mybb->get_input('mindims')),
                        "maxdims" => $db->escape_string($mybb->get_input('maxdims')),
                        "square" => $mybb->get_input('square', MyBB::INPUT_INT),
                        "bytesize" => $db->escape_string($mybb->get_input('bytesize')),
                    );
    
                    $db->update_query("uploadsystem", $edit_uploadelement, "usid='".$usid."'");
    
                    $mybb->input['module'] = "Uploadsystem";
                    $mybb->input['action'] = $lang->uploadsystem_manage_edit_element_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->uploadsystem_manage_edit_element_flash, 'success');
                    admin_redirect("index.php?module=tools-uploadsystem");
                }
            }
    
            $page->add_breadcrumb_item($lang->uploadsystem_manage_edit_element);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1822"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1827"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1805"></script>
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
            $page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_edit_element);
    
            // Übersichtsseite Button
            $sub_tabs['uploadsystem_element_edit'] = [
                "title" => $lang->uploadsystem_manage_edit_element,
                "link" => "index.php?module=tools-uploadsystem&amp;action=edit_element&usid=".$usid,
                "description" => $lang->uploadsystem_manage_edit_element_desc
            ];
    
            $page->output_nav_tabs($sub_tabs, 'uploadsystem_element_edit');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            }
    
            // Build the form
            $form = new Form("index.php?module=tools-uploadsystem&amp;action=edit_element", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->uploadsystem_manage_edit_element_container, $element['name']));
            echo $form->generate_hidden_field('usid', $usid);
    
            // Identifikator
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_identification,
                $lang->uploadsystem_manage_add_upload_identification_desc,
                $form->generate_text_box('identification', $element['identification'])
            );
    
            // Name
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_name,
                $lang->uploadsystem_manage_add_upload_name_desc,
                $form->generate_text_box('name', $element['name'])
            );
    
            // Beschreibung
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_description,
                $lang->uploadsystem_manage_add_upload_description_desc,
                $form->generate_text_box('description', $element['description'])
            );
    
            // Sortierung
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_disporder,
                $lang->uploadsystem_manage_add_upload_disporder_desc,
                $form->generate_numeric_field('disporder', $element['disporder'], array('id' => 'disporder', 'min' => 0))
            );
    
            // erlaubte Dateitypen
            $DBextensions_string = str_replace(", ", ",", $element['allowextensions']);
            $DBextensions_values = explode (",", $DBextensions_string);
       
            $checkbox_extensions = "";
            foreach($extensions_values as $value){
                $big_value = strtoupper($value);
                if (in_array($value, $DBextensions_values)) {
                    $mybb->input[$value] = 1;
                } else {
                    $mybb->input[$value] = 0;
                }
                $checkbox_extensions .= $form->generate_check_box($value, $value, $lang->sprintf($lang->uploadsystem_manage_add_upload_extensions_value, $big_value), array('checked' => $mybb->get_input($value), 'id' => $value)).",";
            }
            $split_checkbox = explode(",", $checkbox_extensions);
            $checkbox_extensions = implode('<br />', $split_checkbox);
            
            $extensions_options = array(
                $checkbox_extensions
            );
            $form_container->output_row($lang->uploadsystem_manage_add_upload_extensions, $lang->uploadsystem_manage_add_upload_extensions_desc, implode('<br />', $extensions_options), '', array(), array('id' => 'row_extensions_options'));
    
            // Minimale Grafik-Größe
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_mindims,
                $lang->uploadsystem_manage_add_upload_mindims_desc,
                $form->generate_text_box('mindims', $element['mindims'])
            );
    
            // Maximale Grafik-Größe
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_maxdims,
                $lang->uploadsystem_manage_add_upload_maxdims_desc,
                $form->generate_text_box('maxdims', $element['maxdims'])
            );
    
            // Quadratisch
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_square,
                $lang->uploadsystem_manage_add_upload_square_desc,
                $form->generate_yes_no_radio('square', $element['square'])
            );
    
            // maximale Dateigröße
            $form_container->output_row(
                $lang->uploadsystem_manage_add_upload_bytesize,
                $lang->uploadsystem_manage_add_upload_bytesize_desc,
                $form->generate_numeric_field('bytesize', $element['bytesize'], array('id' => 'bytesize', 'min' => 0))
            );
    
    
            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->uploadsystem_manage_edit_element_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();
            $page->output_footer();
            exit;
        }

		// ELEMENT LÖSCHEN
		if ($mybb->input['action'] == "delete_element") {

			// Get data
			$usid = $mybb->get_input('usid', MyBB::INPUT_INT);
			$query = $db->simple_select("uploadsystem", "*", "usid='".$usid."'");
			$del_type = $db->fetch_array($query);

			// Error Handling
			if (empty($usid)) {
				flash_message($lang->uploadsystem_manage_error_invalid, 'error');
				admin_redirect("index.php?module=tools-uploadsystem");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=tools-uploadsystem");
			}

			if ($mybb->request_method == "post") {

                // Element aus der DB löschen
				$db->delete_query("uploadsystem", "usid = '".$usid."'");

				// Spalte aus der DB löschen
                if ($db->field_exists($del_type['identification'], "uploadfiles")) {
                    $db->drop_column("uploadfiles", $del_type['identification']);
                }	
                
                // Ordner löschen
                rmdir(MYBB_ROOT.'uploads/uploadsystem/'.$del_type['identification']);

				$mybb->input['module'] = "Uploadsystem";
				$mybb->input['action'] = $lang->uploadsystem_manage_overview_delete_logadmin;
				log_admin_action(htmlspecialchars_uni($del_type['name']));

				flash_message($lang->uploadsystem_manage_overview_delete_flash, 'success');
				admin_redirect("index.php?module=tools-uploadsystem");
			} else {
				$page->output_confirm_action(
					"index.php?module=tools-uploadsystem&amp;action=delete_element&amp;usid=".$usid,
					$lang->uploadsystem_manage_overview_delete_notice
				);
			}
			exit;
		}

        // USER VERWALTEN
        if ($mybb->get_input('action') == "userfiles") {

            $page->add_breadcrumb_item($lang->uploadsystem_manage_userfiles);

			// Optionen im Header bilden
			$page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_userfiles);

			// Übersichtsseite Button
			$sub_tabs['uploadsystem'] = [
				"title" => $lang->uploadsystem_manage_overview,
				"link" => "index.php?module=tools-uploadsystem",
				"description" => $lang->uploadsystem_manage_overview_desc
			];
			// Upload Hinzufüge Button
			$sub_tabs['uploadsystem_upload_add'] = [
				"title" => $lang->uploadsystem_manage_add_upload,
				"link" => "index.php?module=tools-uploadsystem&amp;action=add_upload",
				"description" => $lang->uploadsystem_manage_add_upload_desc
			];
			// Userdatein verwalten Button
			$sub_tabs['uploadsystem_userfiles'] = [
				"title" => $lang->uploadsystem_manage_userfiles,
				"link" => "index.php?module=tools-uploadsystem&amp;action=userfiles",
				"description" => $lang->uploadsystem_manage_userfiles_desc
			];
			// User in DB Button
			$sub_tabs['uploadsystem_usercheck'] = [
				"title" => $lang->uploadsystem_manage_usercheck,
				"link" => "index.php?module=tools-uploadsystem&amp;action=usercheck",
				"description" => $lang->uploadsystem_manage_usercheck_desc
			];

			$page->output_nav_tabs($sub_tabs, 'uploadsystem_userfiles');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			// Übersichtsseite
			$form = new Form("index.php?module=tools-uploadsystem&amp;action=userfiles", "post", "", 1);
			$form_container = new FormContainer($lang->uploadsystem_manage_userfiles);
			// Name
			$form_container->output_row_header($lang->uploadsystem_manage_userfiles_overview, array('style' => 'text-align: left; width: 25%;'));
			// Optionen
			$form_container->output_row_header($lang->uploadsystem_manage_userfiles_options, array('style' => 'text-align: center; width: 10%;'));

            $users_count = $db->num_rows($db->query("SELECT uid FROM ".TABLE_PREFIX."users"));

            $default_perpage = 10;
            $perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
            if(!$perpage){
                $perpage = $default_perpage;
            }

			// Page
            $pageview = $mybb->get_input('page', MyBB::INPUT_INT);
            if ($pageview && $pageview > 0) {
                $start = ($pageview - 1) * $perpage;
            } else {
                $start = 0;
                $pageview = 1;
            }
			
            $end = $start + $perpage;
            $lower = $start+1;
            $upper = $end;
            if($upper > $users_count) {
                $upper = $users_count;
            }

            // Alle User 
			$query_allUser = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadfiles uf
            LEFT JOIN ".TABLE_PREFIX."users u
            ON uf.ufid = u.uid
            ORDER BY u.username ASC, uf.ufid ASC
			LIMIT $start, $perpage
            ");

			while ($users = $db->fetch_array($query_allUser)) {

                $form_container->output_cell('<strong>'.htmlspecialchars_uni($users['username']).'</strong>');

                // OPTIONEN
				$popup = new PopupMenu("uploadsystem_".$users['ufid'], $lang->uploadsystem_manage_userfiles_options);	
                $popup->add_item(
                    $lang->uploadsystem_manage_userfiles_options_edit,
                    "index.php?module=tools-uploadsystem&amp;action=edit_user&amp;ufid=".$users['ufid']
                );$popup->add_item(
                    $lang->uploadsystem_manage_userfiles_options_delete,
                    "index.php?module=tools-uploadsystem&amp;action=delete_user&amp;ufid=".$users['ufid']
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();
            }

			// keine User vorhanden
			if($db->num_rows($query_allUser) == 0){
                $form_container->output_cell($lang->ploadsystem_manage_userfiles_no_user, array("colspan" => 2));
                $form_container->construct_row();
			}

            $form_container->end();
            $form->end();
            // Multipage
            $search_url = htmlspecialchars_uni(
                "index.php?module=tools-uploadsystem&action=userfiles".$mybb->input['perpage']
            );
            $multipage = multipage($users_count, $perpage, $pageview, $search_url);
            echo $multipage;
            $page->output_footer();
			exit;

        }
    
        // USER DATEIEN BEARBEITEN
        if ($mybb->get_input('action') == "edit_user") {
            
            // Get the data
            $ufid = $mybb->get_input('ufid', MyBB::INPUT_INT);

            $user = get_user($ufid);
    
            $page->add_breadcrumb_item($lang->uploadsystem_manage_edit_user);
    
            // Build options header
            $page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_edit_user);
    
			// Userdatein verwalten Button
			$sub_tabs['uploadsystem_userfiles'] = [
				"title" => $lang->uploadsystem_manage_userfiles,
				"link" => "index.php?module=tools-uploadsystem&amp;action=userfiles",
				"description" => $lang->uploadsystem_manage_userfiles_desc
			];
            // User Dateien bearbeiten Button
            $sub_tabs['uploadsystem_user_edit'] = [
                "title" => $lang->uploadsystem_manage_edit_user,
                "link" => "index.php?module=tools-uploadsystem&amp;action=edit_user&ufid=".$ufid,
                "description" => $lang->uploadsystem_manage_edit_user_desc
            ];
    
            $page->output_nav_tabs($sub_tabs, 'uploadsystem_user_edit');

            // DOs
			if ($mybb->request_method == "post") { 

                $errors = array();

                // LÖSCHEN
				if (isset($mybb->input['remove'])) {

                    $identification = $mybb->input['identification'];
                    $name = $db->fetch_field($db->simple_select("uploadsystem", "name", "identification = '".$identification."'"), "name");

                    // Verzeichnis für die Datein
                    $folder_path =  MYBB_ROOT."uploads/uploadsystem/".$identification."/";

                    $filename = $db->fetch_field($db->simple_select("uploadfiles", $identification, "ufid = '".$ufid."'"), $identification);
                    // Datei löschen
                    delete_uploaded_file($folder_path . $filename);
        
                    // DB Eintrag löschen
                    $del_upload = array(
                        $identification => ""
                    );
        
                    $db->update_query("uploadfiles", $del_upload, "ufid = '".$ufid."'");


                    $mybb->input['module'] = "Uploadsystem";
                    $mybb->input['action'] = $lang->sprintf($lang->uploadsystem_manage_edit_user_logadmin_remove, $user['username']);
                    log_admin_action(htmlspecialchars_uni($name)." Datei gelöscht");
    
                    flash_message($lang->sprintf($lang->uploadsystem_manage_edit_user_flash_remove, $name), 'success');
                    admin_redirect("index.php?module=tools-uploadsystem&amp;action=edit_user&amp;ufid=".$ufid);
				}

                // HOCHLADEN
                if (isset($mybb->input['do_upload'])) {
            
                    $usID = $mybb->get_input('usid');
            
                    // Daten zu dem Upload Element
                    $element_query = $db->simple_select("uploadsystem", "*", "usid = '".$usID."'");
                    $element = $db->fetch_array($element_query);
            
                    $identification = $element['identification'];
                    $name = $element['name'];
                    $allowextensions = $element['allowextensions'];
                    $mindims = $element['mindims'];
                    $maxdims = $element['maxdims'];
                    $square = $element['square'];
                    $bytesize = $element['bytesize'];
            
                    // Input Variable Namen
                    $input_name = "pic_".$identification;
                
                    // Verzeichnis für die Datein
                    $folder_path =  MYBB_ROOT."uploads/uploadsystem/".$identification."/";  

                    // Dateityp ermittel (.png, .jpg, .gif)
                    $imageFileType = end((explode(".", $_FILES[$input_name]['name'])));
                
                    // Bildname - Speichern
                    $filename = $identification.'_'.$ufid.'.' . $imageFileType;
            
                    // Hochladen
                    move_uploaded_file($_FILES[$input_name]['tmp_name'], $folder_path . $filename);
            
                    // Grafik-Größe
                    $imgDimensions = @getimagesize($folder_path . $filename);
                    if(!is_array($imgDimensions)){
                        delete_uploaded_file($folder_path . $filename);
                    }
                    // Höhe & Breite
                    $width = $imgDimensions[0];
                    $height = $imgDimensions[1];
            
                    // Überprüfung der Bildgröße
                    list($minwidth, $minheight) = preg_split('/[|x]/', my_strtolower($mindims));
                    list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($maxdims));
            
                    if(!empty($_FILES[$input_name]['name'])) {
                        // Maximal Größe angegeben
                        if (!empty($maxdims)) {
                            // Max und Mini sind gleich groß => feste Bildgröße
                            if ($minwidth == $maxwidth AND $minheight == $maxheight) {
                                if($width != $minwidth || $height != $minheight){	
                                    $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_fixed, $minwidth, $minheight);
                                } 
                            } else {
                                // ob das Bild quadratisch sein muss
                                if ($square == 1) {
                                    // Bild kleiner als minimale Bildgröße
                                    if ($width < $minwidth || $height < $minheight) {
                                        if ($width / $height != 1) {
                                            $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_squareMini, $minwidth, $minheight);    
                                        } else {
                                            $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_mini, $minwidth, $minheight);
                                        }
                                    } 
                                    // Bild größer als maximale Bildgröße
                                    if ($width > $maxwidth || $height > $maxheight) {
                                        if ($width / $height != 1) {
                                            $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_squareMax, $maxwidth, $maxheight);
                                        } else {
                                            $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_max, $maxwidth, $maxheight);
                                        }
                                    }
                                } else {  
                                    // Bild kleiner als minimale Bildgröße
                                    if ($width < $minwidth || $height < $minheight) {
                                        $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_mini, $minwidth, $minheight);
                                    } 
                                    // Bild größer als maximale Bildgröße
                                    if ($width > $maxwidth || $height > $maxheight) {
                                        $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_max, $maxwidth, $maxheight);
                                    }
                                }
                            }
                        } 
                        // nur Minimal Größe 
                        else {
                            // ob das Bild quadratisch sein muss
                            if ($square == 1) {
                                if($width / $height != 1) {
                                    if ($width < $minwidth || $height < $minheight) {
                                        $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_squareMini, $minwidth, $minheight);
                                    } else {
                                        $errors[] = $lang->uploadsystem_manage_edit_user_error_upload_dims_square;
                                    }
                                }
                            } else {
                                if($width < $minwidth || $height < $minheight) {
                                    $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_dims_mini, $minwidth, $minheight);
                                } 
                            }
                
                        }
            
                    }
            
                    // Überprüfung der Dateigröße
                    if ($bytesize > 0) {
                        $max_size = $bytesize*1024; 
                        if($_FILES[$input_name]['size'] > $max_size) {
                            $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_size, get_friendly_size($max_size));
                        }
                    }
                    
                    // Überprüfung der Dateiendung    
                    $extensions_array = explode (", ", $allowextensions);
                    if(!in_array($imageFileType, $extensions_array) AND !empty($_FILES[$input_name]['name'])) {
                        $errors[] = $lang->sprintf($lang->uploadsystem_manage_edit_user_error_upload_file, $imageFileType);
                    }
            
                    // Datei nicht ausgefüllt
                    if(empty($_FILES[$input_name]['name'])) {
                        $errors[] = $lang->uploadsystem_manage_edit_user_error_upload;    
                    }
            
                    // Error darf nicht ausgefüllt sein
                    if(empty($errors)) {
            
                        $file_upload = $db->escape_string($filename);
                    
                        // Eintragen
                        $new_upload = array(
                            $identification => $file_upload
                        );
            
                        $db->update_query("uploadfiles", $new_upload, "ufid = '".$ufid."'");


                        $mybb->input['module'] = "Uploadsystem";
                        $mybb->input['action'] = $lang->sprintf($lang->uploadsystem_manage_edit_user_logadmin_upload, $user['username']);
                        log_admin_action(htmlspecialchars_uni($name)." Datei hochgeladen");
        
                        flash_message($lang->sprintf($lang->uploadsystem_manage_edit_user_flash_upload, $name), 'success');
                        admin_redirect("index.php?module=tools-uploadsystem&amp;action=edit_user&amp;ufid=".$ufid);
                    } else {
                        $mybb->input['action'] = "edit_user";

                        delete_uploaded_file($folder_path . $filename);
                        $del_upload = array(
                            $identification => ''
                        );
                        $db->update_query("uploadfiles", $new_upload, "ufid = '".$ufid."'");
                    }

                }
			}
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            }
    
            // Build the form
            $form = new Form("index.php?module=tools-uploadsystem&amp;action=edit_user", "post", "", 1);
            echo $form->generate_hidden_field('ufid', $ufid);
            $form_container = new FormContainer($lang->sprintf($lang->uploadsystem_manage_edit_user_container, $user['username']));

            $allElements = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadsystem
            ORDER BY disporder ASC, name ASC
            ");

            while($all = $db->fetch_array($allElements)) {

                // Einzelne Px Angaben
                list($minwidth, $minheight) = preg_split('/[|x]/', my_strtolower($all['mindims']));
    
                // Dateiname
                $file_name = $db->fetch_field($db->simple_select("uploadfiles", $all['identification'], "ufid = '".$ufid."'"), $all['identification']);

                // kompletter URL
                if (!empty($file_name)) { 
                    // kompletter URL
                    $file_url = "../".$all['path'].$file_name;
                    $graphic_size = "";
                    $notice = $lang->uploadsystem_manage_edit_user_element_notice;

                    $remove = $form->generate_submit_button($lang->sprintf($lang->uploadsystem_manage_edit_user_element_button_remove, $all['name']), array("name" => "remove"));
                    $upload_button = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_button_change, $all['name']);
                } else {
                    // Placeholder
                    $file_url = "../images/uploadsystem_default.png";
                    $graphic_size = $minwidth."x".$minheight;
                    $notice = "";

                    $remove = "";
                    $upload_button = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_button_add, $all['name']);
                }

                // Größe
                if ($all['maxdims'] != $all['mindims']) {
                    if(!empty($all['maxdims'])) {
                        $dims = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_dims_maxmin, $all['mindims'], $all['maxdims']);
                    } else {
                        $dims = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_dims_min, $all['mindims']);
                    }
                } else {
                    $dims = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_dims_one, $all['mindims']);
                }
    
                // Quadratisch
                if ($all['square'] == 1) {
                    $square = $lang->uploadsystem_manage_edit_user_element_square;
                } else {
                    $square = "";
                }
    
                // Dateigröße
                $size = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_size, get_friendly_size($all['bytesize']*1024));
    
                // Dateiformate
                $extensions_array = explode (", ", $all['allowextensions']);
                if (count($extensions_array) > 1) {
                    $big_extensions = strtoupper($all['allowextensions']);
                    $extensions = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_extensions_plural, $big_extensions);
                } else {
                    $big_extensions = strtoupper($all['allowextensions']);
                    $extensions = $lang->sprintf($lang->uploadsystem_manage_edit_user_element_extensions_singular, $big_extensions);
                }

                // Upload Buttons
                $upload = '<form method="post" action="index.php?module=tools-uploadsystem&amp;action=edit_user&ufid='.$ufid.'" enctype="multipart/form-data">
                <div class="uploadsystem_upload">
                    <div class="uploadsystem_upload_info">
                    <b>'.$lang->sprintf($lang->uploadsystem_manage_edit_user_element_upload, $all['name']).'</b></br>
                        '.$notice.'
                </div>
                <div class="uploadsystem_upload_input">
                    <input type="file" name="pic_'.$all['identification'].'">
                </div>
                <div class="uploadsystem_upload_button">   
                '.$form->generate_hidden_field('my_post_key', $mybb->post_code).'
                '.$form->generate_hidden_field('identification', $all['identification'], array('id' => 'identification')).'  
                '.$form->generate_hidden_field('usid', $all['usid'], array('id' => 'usid')).'                     
                '.$form->generate_submit_button($upload_button, array("name" => "do_upload")).'
                '.$remove.'
                </div>
                </div>
                </form>';
    

                // Gesamtes Element
                $form_container->output_cell('<style type="text/css">.uploadsystem_element_headline{background:#0f0f0f url(\'../../../images/tcat.png\') repeat-x;color:#fff;border-top:1px solid #444;border-bottom:1px solid #000;padding:6px;font-size:12px;margin-bottom:10px}.uploadsystem_element_main{display:flex;gap:10px;flex-wrap:nowrap;align-items:center;justify-content:space-between;padding:0 10px}.uploadsystem_element_info{text-align:justify}.uploadsystem_element_preview{background-size:100%;display:flex;justify-content:center;align-items:center;color:#6f6d6d;font-weight:700}.uploadsystem_upload{margin-top:10px;display:flex;flex-wrap:wrap;gap:10px;padding:0 10px;align-items:center}.uploadsystem_upload_info{width:45%;border-right:1px solid;border-color:#ddd}.uploadsystem_upload_input{width:53%}.uploadsystem_upload_button{width:100%;text-align:center}</style>
                <div class="uploadsystem_element">
                <div class="uploadsystem_element_headline"><strong>'.htmlspecialchars_uni($all['name']).' ändern</strong></div>
                <div class="uploadsystem_element_main">
                    <div class="uploadsystem_element_info">
                    '.htmlspecialchars_uni($all['description']).'</br></br>
                    <small>
                    '.$dims.' '.$square.'<br>
                    '.$extensions.'<br>
                    '.$size.'
                    </small>
                    </div>
                    <div>
                    <div class="uploadsystem_element_preview" style="background:url(\''.$file_url.'\');background-size: cover;width:'.$minwidth.'px;height:'.$minheight.'px;">
                    '.$graphic_size.'
                    </div>
                    </div>
                    </div>
                    '.$upload.'
                    </div>
                ');
    
                $form_container->construct_row();    
            }
    
            $form_container->end();
            $form->end();
            $page->output_footer();
            exit;
        }

        // USER DATEIEN LÖSCHEN
		if ($mybb->input['action'] == "delete_user") {

			// Get data
			$ufid = $mybb->get_input('ufid', MyBB::INPUT_INT);

            $user = get_user($ufid);

			// Error Handling
			if (empty($ufid)) {
				flash_message($lang->uploadsystem_manage_error_invalid, 'error');
				admin_redirect("index.php?module=tools-uploadsystemm&amp;action=userfiles");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=tools-uploadsystemm&amp;action=userfiles");
			}

			if ($mybb->request_method == "post") {
            
                // Verzeichnis für die Icons
                $folder_path =  MYBB_ROOT."uploads/uploadsystem/"; 
            
                $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."uploadsystem");
                $all_identification = [];
                while($allidentification = $db->fetch_array($allidentification_query)) {
                    $all_identification[] = $allidentification['identification'];
                }
            
                $allfiles_query = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadfiles
                WHERE ufid = '".$ufid."'
                ");
            
                $files_names = [];
                while($allfiles = $db->fetch_array($allfiles_query)) {
                    foreach ($all_identification as $identification) {
                        $files_names[] = $identification."/".$allfiles[$identification];
                    }
                }
            
                foreach ($files_names as $filename) {
                    delete_uploaded_file($folder_path . $filename);
                }

                foreach ($all_identification as $identification_del) {
            
                    $change_all = array(
                        $identification_del => ''
                    );
                
                    $db->update_query("uploadfiles", $change_all, "ufid = '".$ufid."'");
                }
                

				$mybb->input['module'] = "Uploadsystem";
				$mybb->input['action'] = $lang->sprintf($lang->uploadsystem_manage_userfiles_delete_logadmin, $user['username']);
				log_admin_action();

				flash_message($lang->sprintf($lang->uploadsystem_manage_userfiles_delete_flash, $user['username']), 'success');
				admin_redirect("index.php?module=tools-uploadsystem&amp;action=userfiles");
			} else {
				$page->output_confirm_action(
					"index.php?module=tools-uploadsystem&amp;action=delete_user&amp;ufid=".$ufid,
					$lang->uploadsystem_manage_userfiles_delete_notice
				);
			}
			exit;
		}

        // DATENBANK AKTUALISIEREN
		if ($mybb->input['action'] == "usercheck") {

            $page->add_breadcrumb_item($lang->uploadsystem_manage_usercheck);

			// Optionen im Header bilden
			$page->output_header($lang->uploadsystem_manage_header." - ".$lang->uploadsystem_manage_usercheck);

			// Übersichtsseite Button
			$sub_tabs['uploadsystem'] = [
				"title" => $lang->uploadsystem_manage_overview,
				"link" => "index.php?module=tools-uploadsystem",
				"description" => $lang->uploadsystem_manage_overview_desc
			];
			// Upload Hinzufüge Button
			$sub_tabs['uploadsystem_upload_add'] = [
				"title" => $lang->uploadsystem_manage_add_upload,
				"link" => "index.php?module=tools-uploadsystem&amp;action=add_upload",
				"description" => $lang->uploadsystem_manage_add_upload_desc
			];
			// Userdatein verwalten Button
			$sub_tabs['uploadsystem_userfiles'] = [
				"title" => $lang->uploadsystem_manage_userfiles,
				"link" => "index.php?module=tools-uploadsystem&amp;action=userfiles",
				"description" => $lang->uploadsystem_manage_userfiles_desc
			];
			// User in DB Button
			$sub_tabs['uploadsystem_usercheck'] = [
				"title" => $lang->uploadsystem_manage_usercheck,
				"link" => "index.php?module=tools-uploadsystem&amp;action=usercheck",
				"description" => $lang->uploadsystem_manage_usercheck_desc
			];

			$page->output_nav_tabs($sub_tabs, 'uploadsystem_usercheck');

			// UPDATE SACHEN
			if ($mybb->request_method == "post") {

				if (isset($mybb->input['update'])) {

                    // neue Accounts hinzufügen
                    $allUsers = $db->query("SELECT uid FROM ".TABLE_PREFIX."users
                    WHERE uid NOT IN(SELECT ufid FROM ".TABLE_PREFIX."uploadfiles)	
                    ");
                    
                    while ($users = $db->fetch_array($allUsers)) {
                
                        $user['upload_system']['ufid'] = $users['uid'];
                    
                        $uscache = $cache->read('uploadsystem');
                    
                        if(is_array($uscache)){
                            foreach($uscache as $upload_file){
                                if(array_key_exists($upload_file['identification'], $user['upload_system'])){
                                    continue;
                                }
                                $user['upload_system'][$upload_file['identification']] = '';
                            }
                        }
                    
                        $db->insert_query("uploadfiles", $user['upload_system'], false);
                    }

				}

                $mybb->input['module'] = "Uploadsystem";
                $mybb->input['action'] = $lang->uploadsystem_manage_usercheck_logadmin;
                log_admin_action();

                flash_message($lang->uploadsystem_manage_usercheck_flash, 'success');
                admin_redirect("index.php?module=tools-uploadsystem");
			}

			// Überprüfen, ob Update nötig ist
            $allUsers = $db->query("SELECT uid FROM ".TABLE_PREFIX."users
            WHERE uid NOT IN(SELECT ufid FROM ".TABLE_PREFIX."uploadfiles)	
            ");
            // Zählen
            $count_users = $db->num_rows($allUsers);

			$form = new Form("index.php?module=tools-uploadsystem&amp;action=usercheck", "post");
			$form_container = new FormContainer($lang->uploadsystem_manage_usercheck);
			// Name
			$form_container->output_row_header($lang->uploadsystem_manage_usercheck_missing);
			// Optionen
			$form_container->output_row_header($lang->uploadsystem_manage_usercheck_option, array('style' => 'text-align: center; width: 30%;'));

			$form_container->output_cell($lang->uploadsystem_manage_usercheck_missing_desc);

			// BUTTON ANZEIGEN ODER NICHT
			if ($count_users > 0) {
				$form_container->output_cell("<center>".$form->generate_submit_button($lang->uploadsystem_manage_usercheck, array("name" => "update"))."</center>");
			} else {
				// alle Accounts vorhanden
				$form_container->output_cell("<center>".$lang->uploadsystem_manage_usercheck_option_full."</center>");
			}

			$form_container->construct_row();

			$form_container->end();
			$form->end();
			$page->output_footer();
			exit;
		}

    }

}

// NEUER USER REGISTIERT SICH => ZEILE IN DB ERSTELLEN
function uploadsystem_user_insert(&$dh){
	
    global $db, $cache, $dh, $user;

    $maxuid = $db->fetch_field($db->query("SELECT MAX(uid) FROM ".TABLE_PREFIX."users"), "MAX(uid)");

    $user['upload_system']['ufid'] = $maxuid;

    $uscache = $cache->read('uploadsystem');

    if(is_array($uscache))
    {
        foreach($uscache as $upload_file)
        {
            if(array_key_exists($upload_file['identification'], $user['upload_system']))
            {
                continue;
            }
            $user['upload_system'][$upload_file['identification']] = '';
        }
    }

    $db->insert_query("uploadfiles", $user['upload_system'], false);
}

// UPLOADS ENTFERNEN VOM GELÖSCHTEN USER
function uploadsystem_user_delete(){

    global $db, $cache, $mybb, $user;

    // UID gelöschter Chara
    $deleteChara = (int)$user['uid'];

    require_once MYBB_ROOT."inc/functions_upload.php";
    require_once MYBB_ROOT."inc/functions.php";

    // Verzeichnis für die Icons
    $folder_path =  MYBB_ROOT."uploads/uploadsystem/"; 

    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."uploadsystem");
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }

    $allfiles_query = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadfiles
    WHERE ufid = '".$deleteChara."'
    ");

    $files_names = [];
    while($allfiles = $db->fetch_array($allfiles_query)) {
        foreach ($all_identification as $identification) {
            $files_names[] = $identification."/".$allfiles[$identification];
        }
    }

    foreach ($files_names as $filename) {
        delete_uploaded_file($folder_path . $filename);
    }

    $db->delete_query('uploadfiles', "ufid = '".$deleteChara."'");
  
}
 
// USERCP - SEITE //
// Menü einfügen
function uploadsystem_nav() {

	global $mybb, $templates, $lang, $usercpmenu;

	$lang->load("uploadsystem");

	eval("\$usercpmenu .= \"".$templates->get("uploadsystem_usercp_nav")."\";");
}

// Die Seite
function uploadsystem_usercp() {

    global $mybb, $db, $plugins, $templates, $theme, $lang, $header, $headerinclude, $footer, $usercpnav, $page, $upload_element, $upload;
   
    // SPRACHDATEI LADEN
    $lang->load("uploadsystem");

    // AKTIVER USER
    $thisuser = $mybb->user['uid'];

    require_once MYBB_ROOT."inc/functions_upload.php";
    require_once MYBB_ROOT."inc/functions.php";

    // UPLOAD EIGENE
    if($mybb->input['action'] == "do_upload" && $mybb->request_method == "post") {

        $uploadsystem_error = array();

        $usID = $mybb->get_input('usID');

        // Daten zu dem Upload Element
        $element_query = $db->simple_select("uploadsystem", "*", "usid = '".$usID."'");
        $element = $db->fetch_array($element_query);

        $identification = $element['identification'];
        $name = $element['name'];
        $allowextensions = $element['allowextensions'];
        $mindims = $element['mindims'];
        $maxdims = $element['maxdims'];
        $square = $element['square'];
        $bytesize = $element['bytesize'];

        // Input Variable Namen
        $input_name = "pic_".$identification;
    
        // Verzeichnis für die Datein
        $folder_path =  MYBB_ROOT."uploads/uploadsystem/".$identification."/"; 

        // Löschen
        if(!empty($mybb->input['remove_upload'])) {

            $filename = $db->fetch_field($db->simple_select("uploadfiles", $identification, "ufid = '".$thisuser."'"), $identification);
            // Datei löschen
            delete_uploaded_file($folder_path . $filename);

            // DB Eintrag löschen
            $del_upload = array(
                $identification => ""
            );

            $db->update_query("uploadfiles", $del_upload, "ufid = '".$thisuser."'");
            redirect("usercp.php?action=uploadsystem", $lang->sprintf($lang->uploadsystem_redirect_remove, $name));

        } 

        // Dateityp ermittel (.png, .jpg, .gif)
        $imageFileType = end((explode(".", $_FILES[$input_name]['name'])));
    
        // Bildname - Speichern
        $filename = $identification.'_'.$thisuser.'.' . $imageFileType;

        // Hochladen
        move_uploaded_file($_FILES[$input_name]['tmp_name'], $folder_path . $filename);

        // Grafik-Größe
        $imgDimensions = @getimagesize($folder_path . $filename);
        if(!is_array($imgDimensions)){
            delete_uploaded_file($folder_path . $filename);
        }
        // Höhe & Breite
        $width = $imgDimensions[0];
        $height = $imgDimensions[1];

        // Überprüfung der Bildgröße
        list($minwidth, $minheight) = preg_split('/[|x]/', my_strtolower($mindims));
        list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($maxdims));

        if(!empty($_FILES[$input_name]['name'])) {
            // Maximal Größe angegeben
            if (!empty($maxdims)) {
                // Max und Mini sind gleich groß => feste Bildgröße
                if ($minwidth == $maxwidth AND $minheight == $maxheight) {
                    if($width != $minwidth || $height != $minheight){	
                        $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_fixed, $minwidth, $minheight);
                    } 
                } else {
                    // ob das Bild quadratisch sein muss
                    if ($square == 1) {
                        // Bild kleiner als minimale Bildgröße
                        if ($width < $minwidth || $height < $minheight) {
                            if ($width / $height != 1) {
                                $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_squareMini, $minwidth, $minheight);    
                            } else {
                                $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_mini, $minwidth, $minheight);
                            }
                        } 
                        // Bild größer als maximale Bildgröße
                        if ($width > $maxwidth || $height > $maxheight) {
                            if ($width / $height != 1) {
                                $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_squareMax, $maxwidth, $maxheight);
                            } else {
                                $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_max, $maxwidth, $maxheight);
                            }
                        }
                    } else {  
                        // Bild kleiner als minimale Bildgröße
                        if ($width < $minwidth || $height < $minheight) {
                            $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_mini, $minwidth, $minheight);
                        } 
                        // Bild größer als maximale Bildgröße
                        if ($width > $maxwidth || $height > $maxheight) {
                            $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_max, $maxwidth, $maxheight);
                        }
                    }
                }
            } 
            // nur Minimal Größe 
            else {
                // ob das Bild quadratisch sein muss
                if ($square == 1) {
                    if($width / $height != 1) {
                        if ($width < $minwidth || $height < $minheight) {
                            $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_squareMini, $minwidth, $minheight);
                        } else {
                            $uploadsystem_error[] = $lang->uploadsystem_error_upload_dims_square;
                        }
                    }
                } else {
                    if($width < $minwidth || $height < $minheight) {
                        $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_dims_mini, $minwidth, $minheight);
                    } 
                }
    
            }

        }

        // Überprüfung der Dateigröße
        if ($bytesize > 0) {
            $max_size = $bytesize*1024; 
            if($_FILES[$input_name]['size'] > $max_size) {
                $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_size, get_friendly_size($max_size));
            }
        }
        
        // Überprüfung der Dateiendung    
        $extensions_array = explode (", ", $allowextensions);
        if(!in_array($imageFileType, $extensions_array) AND !empty($_FILES[$input_name]['name'])) {
            $uploadsystem_error[] = $lang->sprintf($lang->uploadsystem_error_upload_file, $imageFileType);
        }

        // Datei nicht ausgefüllt
        if(empty($_FILES[$input_name]['name'])) {
            $uploadsystem_error[] = $lang->uploadsystem_error_upload;    
        }

        // Error darf nicht ausgefüllt sein
        if(empty($uploadsystem_error)) {

            $file_upload = $db->escape_string($filename);
        
            // Eintragen
            $new_upload = array(
                $identification => $file_upload
            );

            $db->update_query("uploadfiles", $new_upload, "ufid = '".$thisuser."'");
            redirect("usercp.php?action=uploadsystem", $lang->sprintf($lang->uploadsystem_redirect_upload, $name));
        } else {
            $mybb->input['action'] = "uploadsystem";
            $uploadsystem_error = inline_error($uploadsystem_error);

            delete_uploaded_file($folder_path . $filename);
            $del_upload = array(
                $identification => ''
            );
            $db->update_query("uploadfiles", $del_upload, "ufid = '".$thisuser."'");
        }
    }

    // ÜBERSICHT
	if($mybb->input['action'] == "uploadsystem"){

        if(!isset($uploadsystem_error)){
            $uploadsystem_error = "";
        }

        // NAVIGATION
        add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->uploadsystem_usercp, "usercp.php?action=uploadsystem");

        $allElements = $db->query("SELECT * FROM ".TABLE_PREFIX."uploadsystem 
        ORDER BY disporder ASC, name ASC
        ");

        $upload_element = "";
        $upload = "";
        while($all = $db->fetch_array($allElements)) {

            // LEER LAUFEN LASSEN
            $usid = "";
            $identification = "";
            $name = "";
            $description = "";
            $path = "";
            $allowextensions = "";
            $mindims = "";
            $maxdims = "";
            $square = "";
            $bytesize = "";
            $file_name = "";
            $file_url = "";
            $headline = "";
            $remove = "";
            $remove_button = "";

            // MIT INFOS FÜLLEN
            $usid = $all['usid'];
            $identification = $all['identification'];
            $name = $all['name'];
            $description = $all['description'];
            $path = $all['path'];
            $allowextensions = $all['allowextensions'];
            $mindims = $all['mindims'];
            $maxdims = $all['maxdims'];
            $square = $all['square'];
            $bytesize = get_friendly_size($all['bytesize']*1024);

            $headline = $lang->sprintf($lang->uploadsystem_usercp_element_headline, $name);

            // Einzelne Px Angaben
            list($minwidth, $minheight) = preg_split('/[|x]/', my_strtolower($mindims));

            // Dateiname
            $file_name = $db->fetch_field($db->simple_select("uploadfiles", $identification, "ufid = '".$thisuser."'"), $identification);

            // kompletter URL
            if (!empty($file_name)) { 
                // kompletter URL
                $file_url = $path.$file_name;
                $graphic_size = "";

                $element_notice = $lang->uploadsystem_usercp_element_notice;
                $lang->uploadsystem_usercp_element_button = $lang->sprintf($lang->uploadsystem_usercp_element_button_change, $name);

                
                $remove_button = $lang->sprintf($lang->uploadsystem_usercp_element_button_remove, $name);
                eval("\$remove = \"".$templates->get("uploadsystem_usercp_element_remove")."\";");
            } else {
                // Placeholder
                $file_url = $theme['imgdir']."/uploadsystem_default.png";
                $graphic_size = $minwidth."x".$minheight;

                $element_notice = "";
                $lang->uploadsystem_usercp_element_button = $lang->sprintf($lang->uploadsystem_usercp_element_button_add, $name);
                
                $remove_button = "";
                $remove = "";
            }

            // Größe
            if ($maxdims != $mindims) {
                if(!empty($maxdims)) {
                    $dims = $lang->sprintf($lang->uploadsystem_usercp_element_dims_maxmin, $mindims, $maxdims);
                } else {
                    $dims = $lang->sprintf($lang->uploadsystem_usercp_element_dims_min, $mindims);
                }
            } else {
                $dims = $lang->sprintf($lang->uploadsystem_usercp_element_dims_one, $mindims);
            }

            // Quadratisch
            if ($square == 1) {
                $square = $lang->uploadsystem_usercp_element_square;
            } else {
                $square = "";
            }

            // Dateigröße
            $size = $lang->sprintf($lang->uploadsystem_usercp_element_size, $bytesize);

            // Dateiformate
            $extensions_array = explode (", ", $allowextensions);
            if (count($extensions_array) > 1) {
                $big_extensions = strtoupper($allowextensions);
                $extensions = $lang->sprintf($lang->uploadsystem_usercp_element_extensions_plural, $big_extensions);
            } else {
                $big_extensions = strtoupper($allowextensions);
                $extensions = $lang->sprintf($lang->uploadsystem_usercp_element_extensions_singular, $big_extensions);
            }

            // Upload Tpl
            $headline_upload = $lang->sprintf($lang->uploadsystem_usercp_element_upload_headline, $name);
            $subline_upload = $lang->sprintf($lang->uploadsystem_usercp_element_upload_subline, $name);
            eval("\$upload = \"".$templates->get("uploadsystem_usercp_element_upload")."\";");

            eval("\$upload_element .= \"".$templates->get("uploadsystem_usercp_element")."\";");	
        }

        // das template für die ganze Seite 
        eval("\$page= \"".$templates->get("uploadsystem_usercp")."\";");   
        output_page($page);
        die();
	}

}

// ONLINE ANZEIGE - WER IST WO
function uploadsystem_online_activity($user_activity) {

    global $parameters, $user;

    $split_loc = explode(".php", $user_activity['location']);
    if($split_loc[0] == $user['location']) {
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
    }
    
    switch ($filename) {
        case 'usercp':
        if($parameters['action'] == "uploadsystem") {
            $user_activity['activity'] = "uploadsystem";
        }
        break;
    }
    return $user_activity;
}
function uploadsystem_online_location($plugin_array) {

    global $mybb, $theme, $lang;

	$lang->load('uploadsystem');

	if($plugin_array['user_activity']['activity'] == "uploadsystem") {
		$plugin_array['location_name'] = $lang->uploadsystem_onlinelocation;
	}

    return $plugin_array;
}

// AUTOMATISCHE VARIABELN FÜR POSTBIT | MITGLIEDERLISTE | PROFIL | GLOBAL
// Postbit
function uploadsystem_postbit(&$post){
  
    global $fields;

    $uid = $post['uid'];
  
    $fields = uploadsystem_build_view($uid);    
    $post = array_merge($post, $fields);
}

// Mitgliederliste
function uploadsystem_memberlist(&$user){

  global $fields;

  $uid = $user['uid'];

  $fields = uploadsystem_build_view($uid);    
  $user = array_merge($user, $fields);
}

// Profil
function uploadsystem_memberprofile(){

  global $fields, $memprofile;

  $uid = $memprofile['uid'];

  $fields = uploadsystem_build_view($uid);    
  $memprofile = array_merge($memprofile, $fields);
}

// Global
function uploadsystem_global(){

    global $db, $mybb, $upload_data;
  
    $upload_data = array();

    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."uploadsystem");
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }
    
    foreach ($all_identification as $identification) {
        // Inhalt vom Feld
        $fieldvalue = $db->fetch_field($db->simple_select("uploadfiles", $identification, "ufid = '".$mybb->user['uid']."'"), $identification);
        // Pfad
        $path = $db->fetch_field($db->simple_select("uploadsystem", "path", "identification = '".$identification."'"), "path");

        $arraylabel = "files_{$identification}";
        $upload_data[$arraylabel] = $fieldvalue;

        $upload_data[$identification] = $path.$fieldvalue;
    }

}

// Variabel Bau Funktion - danke Katja <3
function uploadsystem_build_view($uid){

  global $db;

  // Rückgabe als Array, also einzelne Variablen die sich ansprechen lassen

    $array = array();
    
    //erst einmal Indientifikatoren bekommen
    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."uploadsystem");
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }
    
    foreach ($all_identification as $identification) {

        // Inhalt vom Feld
        $fieldvalue = $db->fetch_field($db->simple_select("uploadfiles", $identification, "ufid = '".$uid."'"), $identification);

        // Pfad
        $path = $db->fetch_field($db->simple_select("uploadsystem", "path", "identification = '".$identification."'"), "path");

        // Dateiname + Pfad {$variable['identification']}
        $arraylabel = $identification;
        $array[$arraylabel] = $path.$fieldvalue;

        // nur Datei  {$variable['label_vorname']}
        $arraylabel = "files_{$identification}";
        $array[$arraylabel] = $fieldvalue;

    }

    return $array;
}

// Signatur hochladen
function uploadsystem_uploadsig(){

    global $mybb, $db, $lang, $error;

    // EINSTELLUNGEN
    $allowed_extensions = $mybb->settings['uploadsystem_allowed_extensions'];
    $extensions_string = str_replace(", ", ",", $allowed_extensions);
    $extensions_values = explode (",", $extensions_string);
    $signatur_max = $mybb->settings['uploadsystem_signatur_max'];
    $signatur_size = $mybb->settings['uploadsystem_signatur_size'];

    // AKTIVER USER
    $thisuser = $mybb->user['uid'];
    
    // Signatur Pfad
    $folder_path = MYBB_ROOT.'uploads/uploadsystem/signatur';
  
    // Dateiname
    $file_name = $db->fetch_field($db->simple_select("uploadfiles", "signatur", "ufid = '".$thisuser."'"), "signatur");

    $error = array();

    require_once MYBB_ROOT."inc/functions_upload.php";
    require_once MYBB_ROOT."inc/functions.php";

    // Löschen
    if(!empty($mybb->get_input('remove_signatur'))) {

        // Datei löschen
        delete_uploaded_file($folder_path."/".$file_name);

        // DB Eintrag löschen
        $del_sig = array(
            "signatur" => ""
        );

        $db->update_query("uploadfiles", $del_sig, "ufid = '".$thisuser."'");
        redirect("usercp.php?action=editsig", $lang->uploadsystem_signatur_redirect_remove);
    }   

    if(!empty($mybb->get_input('new_signatur'))) { 
        
        // Dateityp ermittel (.png, .jpg, .gif)
        $imageFileType = end((explode(".", $_FILES['signaturlink']['name'])));
    
        // Bildname
        $filename = 'signatur'.'_'.$thisuser.'.'.$imageFileType;

        // Hochladen
        move_uploaded_file($_FILES['signaturlink']['tmp_name'], $folder_path."/".$filename);

        // Grafik-Größe
        $imgDimensions = @getimagesize($folder_path."/".$filename);
        if(!is_array($imgDimensions)){
            delete_uploaded_file($folder_path."/".$filename);
        }
        // Höhe & Breite
        $width = $imgDimensions[0];
        $height = $imgDimensions[1];

        // Überprüfung der Bildgröße
        list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($signatur_max));

        if(!empty($_FILES['signaturlink']['name'])) {
            if (!empty($signatur_max)) {
                if($width > $maxwidth || $height > $maxheight) {
                    $error[] = $lang->sprintf($lang->uploadsystem_signatur_error_upload_dims, $maxwidth, $maxheight);
                } 
            }
        }

        // Überprüfung der Dateigröße
        if ($signatur_size > 0) {
            $max_size = $signatur_size*1024; 
            if($_FILES['signaturlink']['size'] > $max_size) {
                $error[] = $lang->sprintf($lang->uploadsystem_signatur_error_upload_size, get_friendly_size($max_size));
            }
        }
        
        // Überprüfung der Dateiendung    
        if(!in_array($imageFileType, $extensions_values) AND !empty($_FILES['signaturlink']['name'])) {
            $error[] = $lang->sprintf($lang->uploadsystem_signatur_error_upload_file, $imageFileType);
        }

        // Datei nicht ausgefüllt
        if(empty($_FILES['signaturlink']['name'])) {
            $error[] = $lang->uploadsystem_signatur_error_upload;    
        }

        // Error darf nicht ausgefüllt sein
        if(empty($error)) {

            $file_upload = $db->escape_string($filename);
        
            // Eintragen
            $new_upload = array(
                "signatur" => $file_upload
            );

            $db->update_query("uploadfiles", $new_upload, "ufid = '".$thisuser."'");
            redirect("usercp.php?action=editsig", $lang->uploadsystem_signatur_redirect_upload);
        } else {
            
            $errors = implode('\n', $error);
        
            echo "<script>alert('".$errors."');</script>";

            delete_uploaded_file($folder_path."/".$filename);
            $del_upload = array(
                "signatur" => ''
            );
            $db->update_query("uploadfiles", $del_upload, "ufid = '".$thisuser."'");
            redirect("usercp.php?action=editsig", $lang->uploadsystem_signatur_redirect_error);
        }
    }

}

// Signatur Tpl
function uploadsystem_editsig(){

    global $mybb, $db, $templates, $lang, $upload_signatur;
   
    // SPRACHDATEI LADEN
    $lang->load("uploadsystem");

    // EINSTELLUNGEN
    $signatur_setting = $mybb->settings['uploadsystem_signatur'];

    if ($signatur_setting != 1) return;

    // AKTIVER USER
    $thisuser = $mybb->user['uid'];

    // ACTION-BAUM BAUEN
    $mybb->input['action'] = $mybb->get_input('action');

    // Signatur Pfad
    $folder_path = $mybb->settings['homeurl'].'uploads/uploadsystem/signatur/';
  
    // Dateiname
    $file_name = $db->fetch_field($db->simple_select("uploadfiles", "signatur", "ufid = '".$thisuser."'"), "signatur");

    // kompletter URL
    if (!empty($file_name)) { 
        // kompletter URL
        $file_url = $folder_path.$file_name;

        $element_notice = $lang->uploadsystem_usercp_signatur_notice;
        $uploadsystem_usercp_signatur_button = $lang->uploadsystem_usercp_signatur_button_change;

        eval("\$remove = \"".$templates->get("uploadsystem_usercp_signatur_remove")."\";");
    } else {
        $file_url = $lang->uploadsystem_usercp_signatur_nofile;

        $element_notice = "";
        $uploadsystem_usercp_signatur_button = $lang->uploadsystem_usercp_signatur_button_add;

        $remove = "";
    }


    eval("\$upload_signatur .= \"".$templates->get("uploadsystem_usercp_signatur")."\";");	

}

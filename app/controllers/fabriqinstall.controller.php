<?php
/**
 * @file fabriqinstall.controller.php
 * @author Will Steinmetz
 * Fabriq install and update framework functionality
 */

class fabriqinstall_controller extends Controller {
	function __construct() {
		global $installed;
		
		if (((PathMap::action() == 'install') || (PathMap::render_action() == 'install')) && $installed && (PathMap::arg(2) < 4)) {
			global $_FAPP;
			header("Location: " . PathMap::build_path($_FAPP['cdefault'], $_FAPP['adefault']));
			exit();
		} else if (((PathMap::action() == 'install') || (PathMap::render_action() == 'install')) && $installed && (PathMap::arg(2) >= 4)) {
			global $db;
			$query = "SHOW TABLES;";
			$db->query($query);
			$tables = array();
			while ($row = $db->result->fetch_array()) {
				$tables[] = $row[0];
			}
			if (in_array('fabmod_users_users', $tables)) {
				global $_FAPP;
				header("Location: " . PathMap::build_path($_FAPP['cdefault'], $_FAPP['adefault']));
				exit();
			}
		}
		Fabriq::add_css('fabriqinstall', 'screen', 'core/');
	}
	
	public function install() {
		switch (PathMap::arg(2)) {
			case 2:
				$this->install_step2();
			break;
			case 3:
				$this->install_step3();
			break;
			case 4:
				$this->install_step4();
			break;
			case 5:
				$this->install_step5();
			break;
			case 1: default:
				$this->install_step1();
			break;
		}
	}
	
	private function install_step1() {
		Fabriq::title('Start');
	}
	
	private function install_step2() {
		Fabriq::title('Site configuration');
		
		if (isset($_POST['submit'])) {
			if (strlen(trim($_POST['title'])) == 0) {
				Messaging::message('You must enter a page title');
			}
			if (strlen(trim($_POST['title_sep'])) == 0) {
				Messaging::message('You must enter a page title separator');
			}
			if (strlen(trim($_POST['apppath'])) == 0) {
				Messaging::message('You must enter an application path');
			}
			if (!filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
				Messaging::message('You must enter a valid URL');
			}
			
			if (!Messaging::has_messages()) {
				$siteConfig = array(
					'title' => $_POST['title'],
					'title_pos' => $_POST['title_pos'],
					'title_sep' => $_POST['title_sep'],
					'cleanurls' => $_POST['cleanurls'],
					'url' => $_POST['url'],
					'apppath' => $_POST['apppath'],
					'templating' => ($_POST['templating']) ? 'true' : 'false'
				);
				$_SESSION['FAB_INSTALL_site'] = serialize($siteConfig);
				
				// go to next step
				header("Location: index.php?q=fabriqinstall/install/3");
				exit();
			}
			
			FabriqTemplates::set_var('submitted', true);
		}
	}

	private function install_step3() {
		Fabriq::title('Database configuration');
		
		// go back to site configuration step if the session isn't set
		if (!isset($_SESSION['FAB_INSTALL_site']) || ($_SESSION['FAB_INSTALL_site'] == '')) {
			PathMap::arg(2, 2);
			$this->install_step2();
		}
		
		if (isset($_POST['submit'])) {
			if (strlen(trim($_POST['db'])) == 0) {
				Messaging::message('You must enter a database name');
			}
			if (strlen(trim($_POST['user'])) == 0) {
				Messaging::message('You must enter a database user');
			}
			if (strlen(trim($_POST['pwd'])) == 0) {
				Messaging::message('You must enter a database user password');
			}
			if (strlen(trim($_POST['server'])) == 0) {
				Messaging::message('You must enter a database server');
			}
			// test database connectivity
			$mysqli = @mysqli_connect(trim($_POST['server']), trim($_POST['user']), trim($_POST['pwd']), trim($_POST['db']));
			if (!$mysqli) {
				Messaging::message('Error connecting to the database. Please check your database settings and try again.');
			} else {
				mysqli_close($mysqli);
			}
			
			if (!Messaging::has_messages()) {
				$dbConfig = array(
					'db' => trim($_POST['db']),
					'user' => trim($_POST['user']),
					'pwd' => trim($_POST['pwd']),
					'server' => trim($_POST['server'])
				);
				$_SESSION['FAB_INSTALL_db'] = serialize($dbConfig);
				
				// write out configuration file
				$siteConfig = unserialize($_SESSION['FAB_INSTALL_site']);
				$confFile = 'config/config.inc.php';
				$fh = fopen($confFile, 'w');
				fwrite($fh, "<?php\n");
				fwrite($fh, "/**\n");
				fwrite($fh, " * @file\n");
				fwrite($fh, " * Base config file for a Fabriq app.\n");
				fwrite($fh, " */\n\n");
				fwrite($fh, "\$_FAPP = array(\n");
				fwrite($fh, "	'title' => \"{$siteConfig['title']}\",\n");
				fwrite($fh, "	'title_pos' => '{$siteConfig['title_pos']}',\n");
				fwrite($fh, "	'title_sep' => \"{$siteConfig['title_sep']}\",\n");
				fwrite($fh, "	'cleanurls' => {$siteConfig['cleanurls']},\n");
				fwrite($fh, "	'cdefault' => 'homepage',\n");
				fwrite($fh, "	'adefault' => 'index',\n");
				fwrite($fh, "	'url' => '{$siteConfig['url']}',\n");
				fwrite($fh, "	'apppath' => '{$siteConfig['apppath']}',\n");
				fwrite($fh, "	'templating' => {$siteConfig['templating']},\n");
				fwrite($fh, "	'templates' => array(\n");
				fwrite($fh, "		'default' => 'application'\n");
				fwrite($fh, "	)\n");
				fwrite($fh, ");\n\n");
				fwrite($fh, "\$_FDB['default'] = array(\n");
				fwrite($fh, "	'user' => '{$_POST['user']}',\n");
				fwrite($fh, "	'pwd' => '{$_POST['pwd']}',\n");
				fwrite($fh, "	'db' => '{$_POST['db']}',\n");
				fwrite($fh, "	'server' => '{$_POST['server']}'\n");
				fwrite($fh, ");\n");
				fclose($fh);
				
				// create the framework database tables
				global $db;
				$db_info = array(
					'server' => trim($_POST['server']),
					'user' => trim($_POST['user']),
					'pwd' => trim($_POST['pwd']),
					'db' => trim($_POST['db'])
				);
				$db = new Database($db_info);
				// modules table
				$query = "CREATE TABLE IF NOT EXISTS `fabmods_modules` (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`module` varchar(100) NOT NULL,
						`enabled` tinyint(4) NOT NULL,
						`hasconfigs` tinyint(1) NOT NULL,
						`installed` tinyint(1) NOT NULL,
						`versioninstalled` varchar(20) NOT NULL,
						`description` text NOT NULL,
						`dependson` text,
						`created` datetime NOT NULL,
						`updated` datetime NOT NULL,
						PRIMARY KEY (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
				$db->query($query);
				// module configs table
				$query = "CREATE TABLE IF NOT EXISTS `fabmods_module_configs` (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`module` int(11) NOT NULL,
						`var` varchar(100) NOT NULL,
						`val` text NOT NULL,
						`created` datetime NOT NULL,
						`updated` datetime NOT NULL,
						PRIMARY KEY (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
				$db->query($query);
				// module perms table
				$query = "CREATE TABLE IF NOT EXISTS `fabmods_perms` (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`permission` varchar(100) NOT NULL,
						`module` int(11) NOT NULL,
						`created` datetime NOT NULL,
						`updated` datetime NOT NULL,
						PRIMARY KEY (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
				$db->query($query);
				
				// go to next step
				header("Location: index.php?q=fabriqinstall/install/4");
				exit();
			}
			
			FabriqTemplates::set_var('submitted', true);
		}
	}
	
	private function install_step4() {
		Fabriq::title('Core module configuration');
		FabriqTemplates::enable();
		FabriqTemplates::template('fabriqinstall');
		
		Messaging::message('Be sure to continue with module set up in order to complete the install process', 'warning');
		if (!isset($_SESSION['FAB_INSTALL_mods_installed'])) {
			Messaging::message('Configuration file has been written', 'success');
			Messaging::message('Core database tables have been created', 'success');
			FabriqModules::register_module('pathmap');
			FabriqModules::register_module('roles');
			FabriqModules::register_module('users');
			FabriqModules::install('pathmap');
			$module = new Modules();
			$module->getModuleByName('pathmap');
			$module->enabled = 1;
			$module->update();
			Messaging::message('Installed pathmap module', 'success');
			FabriqModules::install('roles');
			$module = new Modules();
			$module->getModuleByName('roles');
			$module->enabled = 1;
			$module->update();
			Messaging::message('Installed roles module', 'success');
			FabriqModules::install('users');
			$module = new Modules();
			$module->getModuleByName('users');
			$module->enabled = 1;
			$module->update();
			Messaging::message('Installed users module', 'success');
			$_SESSION['FAB_INSTALL_mods_installed'] = true;
		}
		
		if (isset($_POST['submit'])) {
			$emailPattern = '/^([a-z0-9])(([-a-z0-9._])*([a-z0-9]))*\@([a-z0-9])(([a-z0-9-])*([a-z0-9]))+' . '(\.([a-z0-9])([-a-z0-9_-])?([a-z0-9])+)+$/i';
			$displayPattern = '/([A-z0-9]){6,24}/';
			$user = FabriqModules::new_model('users', 'Users');
			$user->display = $_POST['display'];
			$user->email = $_POST['email'];
			$user->encpwd = $_POST['pwd'];
			
			if (!preg_match($displayPattern, $user->display)) {
				Messaging::message("Display name is invalid");
			}
			if (!preg_match($emailPattern, $user->email)) {
				Messaging::message("e-mail address is invalid");
			}
			if ((strlen($user->encpwd) < 8) || ($user->encpwd == $user->display) || ($user->encpwd == $user->email)) {
				Messaging::message("Password is invalid");
			}
			
			if (!Messaging::has_messages()) {
				$user->status = 1;
				$user->banned = 0;
				$user->forcepwdreset = 0;
				$user->id = $user->create();
				$user->encpwd = crypt($user->encpwd, $user->id);
				$user->update();
				
				global $_FAPP;
				$url = $_FAPP['url'] . PathMap::build_path('users', 'login');
				$message = <<<EMAIL
Hello {$user->display},

Your account has been created on the {$_FAPP['title']} website.

You can log in by navigating to {$url} in your browser.

Thanks,
The {$_FAPP['title']} team


NOTE: Do not reply to this message. It was automatically generated.
EMAIL;
				mail(
					$user->email,
					"Your account at {$_FAPP['title']}",
					$message,
					'From: noreply@' . str_replace('http://', '', str_replace('https://', '', str_replace('www.', '', $_FAPP['url'])))
				);
				
				// go to next step
				header("Location: index.php?q=fabriqinstall/install/5");
				exit();
			}
			
			FabriqTemplates::set_var('submitted', true);
		}
	}
	
	private function install_step5() {
		Fabriq::title('Install complete');
		FabriqTemplates::enable();
		FabriqTemplates::template('fabriqinstall');
		
		// delete session variables
		unset($_SESSION['FAB_INSTALL_site']);
		unset($_SESSION['FAB_INSTALL_db']);
		unset($_SESSION['FAB_INSTALL_mods_installed']);
	}
	
	public function update() {
		
	}
	
	private function update_step1() {
		
	}
	
	private function update_step2() {
		
	}
	
	private function update_step3() {
		
	}
	
	private function update_step4() {
		
	}
} 

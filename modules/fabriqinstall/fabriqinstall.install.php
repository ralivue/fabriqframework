<?php
/**
 * @file fabriqinstall.install.php
 * @author Will Steinmetz
 * fabriqinstall install file
 */

class fabriqinstall_install extends FabriqModuleInstall {
	public function info() {
		return array(
			"module" => "fabriqinstall",
			"version" => $this->getLatestUpdate(),
			"author" => "Will Steinmetz",
			"description" => "This module manages installing and updating Fabriq."
		);
	}
	
	public function install() {
		$mod = new Modules();
		$mod->getModuleByName('fabriqinstall');
		$perms = array(
			'update Fabriq'
		);
		
		$perm_ids = FabriqModules::register_perms($mod->id, $perms);
		
		// map paths
		$pathmap = &FabriqModules::module('pathmap');
		$pathmap->register_path('fabriqinstall', 'fabriqinstall', 'install', 'module');
		$pathmap->register_path('fabriqinstall/install', 'fabriqinstall', 'install', 'module');
		$pathmap->register_path('fabriqinstall/install/!#', 'fabriqinstall', 'install', 'module', null, 2);
		$pathmap->register_path('fabriqinstall/update', 'fabriqinstall', 'update', 'module');
		$pathmap->register_path('fabriqinstall/update/!#', 'fabriqinstall', 'update', 'module', null, 2);
		
		// give administrators the ability to update the framework
		$adminPerm = FabriqModules::new_model('roles', 'ModulePerms');
		$adminPerm->permission = $perm_ids[0];
		$adminRole = FabriqModules::new_model('roles', 'Roles');
		$adminRole->getRole('administrator');
		$adminPerm->role = $adminRole->id;
		$adminPerm->id = $adminPerm->create();
		
		// set module as installed
		$mod->installed = 1;
		$mod->update();
	}
	
	public function uninstall() {
		// core modules cannot be uninstalled
	}
	
	public function update_2_1_4() {
		// update the module version number
		$mod = new Modules();
		$mod->getModuleByName('fabriqinstall');
		$mod->versioninstalled = '2.1.4';
		$mod->update();
	}
	
	public function update_2_3_1() {
		// update the path(s) for the fabriqupdate module to point to the proper actions
		$pathmap = &FabriqModules::module('pathmap');
		$pathmap->remove_path('fabriqupdates');
		$pathmap->register_path('fabriqupdates', 'fabriqinstall', 'fetchUpdates', 'module');
		
		// update the module version number
		$mod = new Modules();
		$mod->getModuleByName('fabriqinstall');
		$mod->versioninstalled = '2.3.1';
		$mod->update();
	}
}
	
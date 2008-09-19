<?php

	Class extension_timefield extends Extension{
	
		public function about(){
			return array('name' => 'Field: Time',
						 'version' => '1.4',
						 'release-date' => '2008-04-15',
						 'author' => array('name' => 'Symphony Team',
										   'website' => 'http://www.symphony21.com',
										   'email' => 'team@symphony21.com')
				 		);
		}
		
		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_time`");
		}


		public function install(){

			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_time` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `field_id` (`field_id`)
			) TYPE=MyISAM");

		}
			
	}


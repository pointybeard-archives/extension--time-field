<?php
	
	Class fieldTime extends Field{	
	
		const SIMPLE = 0;
		const REGEXP = 1;
		const RANGE = 3;
		const ERROR = 4;	
		
		function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Time';
			$this->_required = true;
			$this->set('required', 'yes');
		}

		function isSortable(){
			return true;
		}
		
		function canFilter(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}
		
		function allowDatasourceParamOutput(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}


		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){
			
			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));
			
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`seconds` $order");
		}

		function groupRecords($records){
			
			if(!is_array($records) || empty($records)) return;
			
			$groups = array($this->get('element_name') => array());
			
			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				
				$value = $data['value'];
				
				if(!isset($groups[$this->get('element_name')][$value])){
					$groups[$this->get('element_name')][$value] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}	
																					
				$groups[$this->get('element_name')][$value]['records'][] = $r;
								
			}

			return $groups;
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);
		}

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
			$element = new XMLElement($this->get('element_name'), $data['value']);
			$element->setAttribute('seconds', (int)$data['seconds']);
			$wrapper->appendChild($element);
		}
		
		public function prepareTableValue($data, XMLElement $link=NULL){
			
			$value = $data['value'];

			if($link){
				$link->setValue($value);
				return $link->generate();
			}
			
			else return $value;
			
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$value = (int)$data['seconds'];	
	
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, self::timeIntToString($value)));

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		public function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>'.$this->Name().'</i>'));
			$label = Widget::Label('Value');
			$label->appendChild(Widget::Input('fields[filter]'.($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '').'['.$this->get('id').']'.($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : NULL)));	
			$wrapper->appendChild($label);
			
			$wrapper->appendChild(new XMLElement('p', 'To filter by ranges, use the format <code>HH:MM:SS to HH:MM:SS</code>', array('class' => 'help')));
			
		}
		
		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;
			
			if($this->get('required') == 'yes' && strlen($data) == 0){
				$message = "This is a required field.";
				return self::__MISSING_FIELDS__;
			}

			if(!preg_match('@^\d{1,2}:\d{1,2}(:\d+)?@', $data)){
				$message = "Time must be entered in the format <code>HH:MM:SS</code>";
				return self::__INVALID_FIELDS__;	
			}
						
			return self::__OK__;		
		}
		
		public function createTable(){
			
			return $this->Database->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `seconds` bigint(20) default NULL,
				  `value` varchar(20) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `seconds` (`seconds`)
				) TYPE=MyISAM;"
			
			);
		}		

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			
			$status = self::__OK__;
			
			return array(
				'seconds' => self::timeStringToInt($data),
				'value' => self::timeIntToString(self::timeStringToInt($data)),
			);
		}
		
		public static function timeStringToInt($string){
			preg_match_all('/^(\d{1,2}):(\d{1,2})(:(\d+))?$/', $string, $matches, PREG_SET_ORDER);		
			return (((int)$matches[0][1] * 3600) + ((int)$matches[0][2] * 60) + (int)$matches[0][4]);
		}

		public static function parseTimeInt($int){
			$hours = floor(($int * (1/60)) * (1/60));
			$minutes = floor(($int - ($hours * 3600)) * (1/60));
			$seconds = $int - (($hours * 3600) + ($minutes * 60));
			
			return array($hours, $minutes, $seconds);
		}
		
		public static function timeIntToString($int){
			
			list($hours, $minutes, $seconds) = self::parseTimeInt($int);
			
			$hours = max(0, (int)$hours);
			$minutes = max(0, (int)$minutes);
			$seconds = max(0, (int)$seconds);
			
			return "$hours:".($minutes < 10 ? '0' : NULL)."$minutes:" . ($seconds < 10 ? '0' : NULL) . $seconds;
		}
		
		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){
			
			## Check its not a regexp
			if(preg_match('/\bto\b/i', $data[0])){
				
				$field_id = $this->get('id');
				
				list($from, $to) = preg_split('/\bto\b/i', $data[0]);
				
				$from = trim($from);
				$to = trim($to);
				
				$from_sec = self::timeStringToInt($from);
				$to_sec = self::timeStringToInt($to);
				
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (t$field_id.`value` > $from_sec AND t$field_id.`value` < $to_sec) ";

			}			
			
			else{
				$data[0] = self::timeStringToInt($data[0]);
				parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);
			}
			
			return true;
			
		}
	
				
	}

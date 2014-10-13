<?php

/**
 * Dimension controller
 *
 * @version 1.0
 * @author Diego Castiglioni <diego.castiglioni@fengoffice.com>
 */
class DimensionController extends ApplicationController {

	/**
	 * Prepare this controller
	 *
	 * @param void
	 * @return ProjectController
	 */
	function __construct() {
		parent::__construct();
		prepare_company_website_controller($this, 'website');
	} // __construct
	

	/**
	 * Gets all the dimensions that user can see plus those wich must be displayed in the panels 
	 * 
	 */
	function get_context(){
		ajx_current("empty");

		// User config root dimensions
		$dids = explode ("," ,user_config_option('root_dimensions', null, logged_user()->getId() ));
		foreach ($dids as  $id) {
			if (is_numeric($id) && $id > 0 ) {
				$user_root_dimensions[$id] = true ;
			}
		}		
		
		//All dimensions
		$all_dimensions = Dimensions::findAll(array('order'=>'default_order ASC , id ASC'));
		$dimensions_to_show = array();
		
		
		$contact_pg_ids = ContactPermissionGroups::getPermissionGroupIdsByContactCSV(logged_user()->getId(),false);
		
		foreach ($all_dimensions as $dim){
			$did = $dim->getId();
			if (isset($user_root_dimensions) && count($user_root_dimensions)) {
				if ( isset($user_root_dimensions[$did]) && $user_root_dimensions[$did] ){
					$dim->setIsRoot(true);
				}else{
					$dim->setIsRoot(false);
				}
			} 
					
			$added=false;
			
			if (!$dim->getDefinesPermissions()){
				$dimensions_to_show ['dimensions'][] = $dim;
				$added = true;
			}
			else{
				/*if dimension does not deny everything for each contact's PG, show it*/
				if (!$dim->deniesAllForContact($contact_pg_ids)  || logged_user()->isAdministrator()){
					$dimensions_to_show ['dimensions'][] = $dim;
					$added = true;
				}
			}
			if ($dim->getIsRoot()&& $added){
					$dimensions_to_show ['is_root'][] = true;
			}
			
		}
		return $dimensions_to_show;
	}
	
	/** 
	 * Returns the dimension members the user has permission to with latest activity so that they can be displayed
	 *  
	 * $dimension_id = id of the dimension to look at
	 * $object_type_id = id of the dimension member type
	 * $logs_amount_range = amount of logs to look for in the application_logs
	 * $minimum_display = minimum amount of dimension members to return, otherwise return all
	 * $maximum_display = maximum amount of dimension members to return  
	*/
	function latest_active_dimension_members($dimension_id, $object_type_id, $allowed_member_type_ids = null, $logs_amount_range="1000", $minimum_display, $maximum_display) {
		//sql query created to filter the members with latest activity through the $extra_conditions variable below
        $sql = "SELECT DISTINCT `".TABLE_PREFIX."object_members`.`member_id`,`".TABLE_PREFIX."application_logs`.`id`
				FROM `".TABLE_PREFIX."application_logs`, `".TABLE_PREFIX."object_members`, `".TABLE_PREFIX."members`
				WHERE (`".TABLE_PREFIX."application_logs`.`rel_object_id` = `".TABLE_PREFIX."object_members`.`object_id`) 
					  AND (`".TABLE_PREFIX."object_members`.`member_id` = `".TABLE_PREFIX."members`.`id` AND `".TABLE_PREFIX."members`.`object_type_id` = '".mysql_real_escape_string($object_type_id)."')
				ORDER BY `".TABLE_PREFIX."application_logs`.`id` DESC LIMIT ".mysql_real_escape_string($logs_amount_range);
        $members_to_filter = DB::executeAll($sql);
        $member_amount = 0;
        //if the dimension members in the search are below the minimum amount to be displayed, show all dimension members the user can access to
        if (is_array($members_to_filter)){
        	$members_to_filter_string = '';
        	foreach ($members_to_filter as $row) {
        		//do not repeat member_ids that already are in the array
        		if (!stristr($members_to_filter_string, ($row['member_id']))){
        			$members_to_filter_string .= "'".$row['member_id']."',";
        			$member_amount++;
        		}
        		//show only up to the limit specified
        		if ($member_amount >= $maximum_display) break;
        	}
        	$members_to_filter_string = substr_replace($members_to_filter_string ,"",-1);
        }
        if ($member_amount > $minimum_display){
            $extra_conditions = " AND id IN (".$members_to_filter_string.")";
		}else{
			$extra_conditions = "";
		}
        return $this->initial_list_dimension_members($dimension_id, $object_type_id,$allowed_member_type_ids, false, $extra_conditions);
	}
	
	/**
	 * Returns all the members to be displayed in the panel that corresponds to the dimension for which the id is received by
	 * parameter.
	 * It is called when the application is first loaded.
	 * @todo: return only the members that are going to be retrieved
	 * @todo: add a function to retrieve the rest of the members - dimension_members - and make it more efficient
	 * @todo: add a funciton to retrieve a specific set of members
	 * @todo: check where this function is called
	 * @todo: check (and fix) that the system doesn't use the left-panel navigation tree to get member's data
	 *
	 */
	function initial_list_dimension_members($dimension_id, $object_type_id, $allowed_member_type_ids = null, $return_all_members = false, $extra_conditions = "", $limit=null, $return_member_objects = false, $order=null, $return_only_members_name=false, $filter_by_members=array(), $access_level=ACCESS_LEVEL_READ, $use_member_cache=false){
		$allowed_member_types = array();
		$item_object = null ;
		if(logged_user()->isAdministrator())$return_all_members=true;
		$contact_pg_ids = ContactPermissionGroups::getPermissionGroupIdsByContactCSV(logged_user()->getId(),false);
		$dimension = Dimensions::getDimensionById($dimension_id);
		
		if ($object_type_id != null){
			$dimension_object_type_contents = $dimension->getObjectTypeContent($object_type_id);
			foreach ($dimension_object_type_contents as $dotc){
				$dot_id = $dotc->getDimensionObjectTypeId();
				if (is_null($allowed_member_type_ids) || in_array($dot_id, $allowed_member_type_ids)) {
					$allowed_member_types[] = $dot_id;
				}
			}
			
			$object_type = ObjectTypes::findById($object_type_id);
			if ($object_type instanceof ObjectType && $object_type->getType() == 'dimension_object' ) {
				eval('$ot_manager = '.$object_type->getHandlerClass().'::instance();');
				if (isset($ot_manager)) {
					eval('$item_object = new '.$ot_manager->getItemClass().'();');
				}
			}
		}
		if ($dimension instanceof Dimension){
			if (count($allowed_member_types) > 0) {
				$extra_conditions = " AND object_type_id IN (".implode(",",$allowed_member_types).")" . $extra_conditions;
			}
			$parent = 0;
			if (is_null($order)) $order = "parent_member_id, name";
			if (!$dimension->getDefinesPermissions() || $dimension->hasAllowAllForContact($contact_pg_ids) || $return_all_members){
				$all_members = $dimension->getAllMembers(false, $order, true, $extra_conditions, $limit);
			}
			else if ($dimension->hasCheckForContact($contact_pg_ids)){
				if($use_member_cache){
					//use the contact member cache
					$params = array(
							"dimension" => $dimension,
							"contact_id" => logged_user()->getId(),
							"parent_member_id" => 0
					);
					$all_members = ContactMemberCaches::getAllMembersWithCachedParentId($params);
									
				}else{
					$member_list = $dimension->getAllMembers(false, $order, true, $extra_conditions, $limit);
					$allowed_members = array();
					foreach ($member_list as $dim_member){
						if (ContactMemberPermissions::instance()->contactCanAccessMemberAll($contact_pg_ids, $dim_member->getId(), logged_user(), $access_level)) {
							$allowed_members[] = $dim_member;
						}
					}
					$all_members = $allowed_members;
				}
			}
			if (!isset($all_members)) {
				$all_members = array();	
			}
			
			$tmp_array = array();
			foreach ($filter_by_members as $filter_id) {
				if ($filter_id) $tmp_array[] = $filter_id;
			}
			$filter_by_members = $tmp_array;
			
			$all_members = $this->apply_association_filters($dimension, $all_members, $filter_by_members);
			
			if ($return_member_objects) {
				return $all_members;
			} else {
				return $this->buildMemberList($all_members, $dimension, $allowed_member_type_ids,$allowed_member_types, $item_object, $object_type_id, $return_only_members_name);
			}
		}
		return null;
	}
	
	
	function apply_association_filters($dimension, $dimension_members, $selected_members) {
		
		$members_to_retrieve = array();
		$all_associated_members_ids = array();
		
		foreach ($selected_members as $member) {
			if (!$member instanceof Member) continue;
			$association_ids = DimensionMemberAssociations::getAllAssociationIds($member->getDimensionId(), $dimension->getId());
			
			if (count($association_ids) > 0) {
				$associated_members_ids = array();
				
				foreach ($association_ids as $id){
					$property_members_members = null;
					$property_members_props = null;
					
					$association = DimensionMemberAssociations::findById($id);
					$children = $member->getAllChildrenInHierarchy();
					
					if ($association->getDimensionId() == $dimension->getId()){
						if (is_null($property_members_members)) $property_members_members = MemberPropertyMembers::getAllPropertyMembers($id);
						
						$tmp_assoc_member_ids = array_var($property_members_members, $member->getId(), array());
						foreach ($children as $child){
							$tmp_assoc_member_ids = array_merge($tmp_assoc_member_ids, array_var($property_members_members, $child->getId(), array()));
						}
						
						$associated_members_ids = array_unique(array_merge($associated_members_ids, $tmp_assoc_member_ids));
					
					} else {
						if (is_null($property_members_props)) $property_members_props = MemberPropertyMembers::getAllPropertyMembers($id, true);
						
						$tmp_assoc_member_ids = array_var($property_members_props, $member->getId(), array());
						foreach ($children as $child){
							$tmp_assoc_member_ids = array_merge($tmp_assoc_member_ids, array_var($property_members_props, $child->getId(), array()));
						}
						
						$associated_members_ids = array_unique(array_merge($associated_members_ids, $tmp_assoc_member_ids));
					
					}
				}
				
				$all_associated_members_ids[] = array_unique($associated_members_ids);
			
			}
		}
		
		
		if (count($all_associated_members_ids) > 0) {
			$intersection = array_var($all_associated_members_ids, 0);
			if (count($all_associated_members_ids) > 1) {
	    		$k = 1;
	    		while ($k < count($all_associated_members_ids)) {
	    			$intersection = array_intersect($intersection, $all_associated_members_ids[$k++]);
	    		}
	    	}
	    	
	    	$all_associated_members_ids = $intersection;
		
		
			if (count($all_associated_members_ids) > 0) {
				$dimension_member_ids = array();
				foreach ($dimension_members as $dm) {
					$dimension_member_ids[] = $dm->getId();
				}
				
				$members_to_retrieve_ids = array();
				$associated_members = Members::findAll(array('conditions' => 'id IN ('.implode(',', $all_associated_members_ids).')'));
				foreach ($associated_members as $associated_member){
	
					$context_hierarchy_members = $associated_member->getAllParentMembersInHierarchy(true);
					foreach ($context_hierarchy_members as $context_member){
						if (!in_array($context_member->getId(), $members_to_retrieve_ids) && in_array($context_member->getId(), $dimension_member_ids)) {
							$members_to_retrieve [$context_member->getName()."_".$context_member->getId()] = $context_member;
							$members_to_retrieve_ids[] = $context_member->getId();
						}
					}
					
				}
				// alphabetical order
				$members_to_retrieve = array_ksort($members_to_retrieve);
				
			}
		
		} else {
			$members_to_retrieve = $dimension_members;
		}
		
		return $members_to_retrieve;
	}

	
	
	function initial_list_dimension_members_tree() {
		$dimension_id = array_var($_REQUEST, 'dimension_id');
		$checkedField = (array_var($_REQUEST, 'checkboxes'))?"checked":"_checked";
		$objectTypeId = array_var($_REQUEST, 'object_type_id', null );
		
		$allowedMemberTypes = json_decode(array_var($_REQUEST, 'allowedMemberTypes', null ));	
		if (!is_array($allowedMemberTypes)) {
			$allowedMemberTypes = null;
		}
		
		$only_names = array_var($_REQUEST, 'onlyname', false);
		
		$name = trim(array_var($_REQUEST, 'query', ''));
		$extra_cond = $name == "" ? "" : " AND name LIKE '%".$name."%'";
		
		$return_all_members = false;
		
		$selected_member_ids = json_decode(array_var($_REQUEST, 'selected_ids', "[0]"));
		$selected_members = Members::findAll(array('conditions' => 'id IN ('.implode(',',$selected_member_ids).')'));
		
		$memberList = $this->initial_list_dimension_members($dimension_id, $objectTypeId, $allowedMemberTypes, $return_all_members, $extra_cond, null, false, null, $only_names, $selected_members);
		
		$tree = buildTree($memberList, "parent", "children", "id", "name", $checkedField);
		
		ajx_current("empty");		
		ajx_extra_data(array('dimension_members' => $tree, 'dimension_id' => $dimension_id));	
	}
	
	//return only root members
	function initial_list_dimension_members_tree_root() {
		$dimension_id = array_var($_REQUEST, 'dimension_id');
		$checkedField = (array_var($_REQUEST, 'checkboxes'))?"checked":"_checked";
		$objectTypeId = array_var($_REQUEST, 'object_type_id', null );
	
		$allowedMemberTypes = json_decode(array_var($_REQUEST, 'allowedMemberTypes', null ));
		if (!is_array($allowedMemberTypes)) {
			$allowedMemberTypes = null;
		}
	
		$only_names = array_var($_REQUEST, 'onlyname', false);
	
		$name = trim(array_var($_REQUEST, 'query', ''));
		
		$extra_cond = $name == "" ? "" : " AND name LIKE '%".$name."%'";
		
		$use_member_cache= true;
		//Super admins are not using the contact member cache
		if(logged_user()->isAdministrator()){
			$extra_cond .= "AND `parent_member_id`=0";
			$use_member_cache= false;
		}
		$return_all_members = false;
	
		$selected_member_ids = json_decode(array_var($_REQUEST, 'selected_ids', "[0]"));
		$selected_members = Members::findAll(array('conditions' => 'id IN ('.implode(',',$selected_member_ids).')'));
		
		$memberList = $this->initial_list_dimension_members($dimension_id, $objectTypeId, $allowedMemberTypes, $return_all_members, $extra_cond, null, false, null, $only_names, $selected_members,null,true);
		
		$tree = buildTree($memberList, "parent", "children", "id", "name", $checkedField);
	
		ajx_current("empty");
		
		//$dids = explode ("," ,user_config_option('root_dimensions', null, logged_user()->getId() ));
		//if(in_array($dimension_id, $dids)){
		ajx_extra_data(array('dimension_members' => $tree, 'dimension_id' => $dimension_id));
		//}
	}
	
	//serach members by name
	function search_dimension_members_tree() {
		$dimension_id = array_var($_REQUEST, 'dimension_id');
		$dimension = Dimensions::getDimensionById($dimension_id);
		$name = trim(array_var($_REQUEST, 'query', ''));
		$random = trim(array_var($_REQUEST, 'random', 0));
		$start = array_var($_REQUEST, 'start' , 0);
		$limit = array_var($_REQUEST, 'limit');
		$order = array_var($_REQUEST, 'order', 'id');
		$parents = array_var($_REQUEST, 'parents' , true);

		if(strlen($name) > 0 || $random){
			//get the member list
			//Super admins are not using the contact member cache
			if(logged_user()->isAdministrator() || !$dimension->getDefinesPermissions()){
				$limit_t = '';
				if(isset($limit)){
					$limit_t = $limit+1;
				}
				
				$search_name_cond = "";
				if(!$random){
					$name = mysql_real_escape_string($name);
					$search_name_cond = " AND name LIKE '%".$name."%'";
				}
				$memberList = Members::findAll(array('conditions' => array("`dimension_id`=? ".$search_name_cond, $dimension_id), 'order' => '`'.$order.'` ASC', 'offset' => $start, 'limit' => $limit_t));
				//include all parents
				//Check hierarchy
				if($parents){
					$members_ids = array();
					$parent_members = array();
					foreach ($memberList as $mem){
						$members_ids[] = $mem->getId();
					}
					foreach ($memberList as $mem){
						$parents = $mem->getAllParentMembersInHierarchy(false);
						foreach ($parents as $parent){
							if(!in_array($parent->getId(), $members_ids)){
								$members_ids[] = $parent->getId();	
								$parent_members[] = $parent;
							}
						}				
					}
					$memberList = array_merge($memberList,$parent_members);
				}
			}else{
				//Use contact member cache
				$params = array();
				$params["dimension"] = $dimension;
				$params["contact_id"] = logged_user()->getId();
				$params["get_all_parent_in_hierarchy"] = $parents;
				$params["order"] = $order;
				if(!$random){
					$params["member_name"] = $name;
				}
				if(isset($limit)){
					$params["start"] = $start;
					$params["limit"] = $limit + 1;
				}
				
				$memberList = ContactMemberCaches::getAllMembersWithCachedParentId($params);
			}
			
			//show more
			$show_more = false;
			if(isset($limit) && count($memberList) > $limit){
				array_pop($memberList); 
				$show_more = true;
			}			
			
			if(!empty($memberList)){
				$allMemebers = $this->buildMemberList($memberList, $dimension, array(),array(), null, null);
						
				if(isset($limit)){
					ajx_extra_data(array('show_more' => $show_more));
				}

				$row = "search-result-row-medium";
				if(!$dimension->canHaveHierarchies()){
					$row = "search-result-row-small";
				}
				ajx_extra_data(array('row_class' => $row));
				ajx_extra_data(array('members' => $allMemebers));
			}
		}
		ajx_extra_data(array('dimension_id' => $dimension_id));
		ajx_current("empty");			
	}
	
	function reload_dimensions_js () {
		ajx_current("empty");
		$dimensions = Dimensions::findAll();
		
		$ot_extra_cond = "";
		Hook::fire('available_object_types_extra_cond', null, $ot_extra_cond);
		$ots = ObjectTypes::getAvailableObjectTypesWithTimeslots($ot_extra_cond);
		
		$dims_info = array();
		$perms_info = array();
		foreach ($dimensions as $dim) {
			$dims_info[$dim->getId()] = array();
			$perms_info[$dim->getId()] = array();
			$members = $dim->getAllMembers();
			foreach ($members as $member) {
				$mem_info = array();
				$mem_info['id'] = $member->getId();
				$mem_info['name'] = clean($member->getName());
				$mem_info['ot'] = $member->getObjectTypeId();
				$mem_info['path'] = $dim->getIsManageable() ? trim(clean($member->getPath())) : '';
				$mem_info['ico'] = $member->getIconClass();
				$mem_info['color'] = $member->getMemberColor();
				$mem_info['parent_id'] = $member->getParentMemberId();
				$mem_info['archived'] = $member->getArchivedById();
								
				$p_info = array();
				if ($dim->getIsManageable()) {
					foreach ($ots as $ot) {
						$p_info[$ot->getId()] = $dim->getDefinesPermissions() ? can_read(logged_user(), array($member), $ot->getId()): true;
					}
				}
				
				$dims_info[$dim->getId()][$member->getId()] = $mem_info;
				$perms_info[$dim->getId()][$member->getId()] = $p_info;
			}
		}
		ajx_extra_data(array("dims" => $dims_info, "perms" => $perms_info));
	}
	
	function load_dimensions_info() {
		ajx_current("empty");
		$dimensions = Dimensions::findAll();
		
		$dim_names = array();
		foreach ($dimensions as $dim) {
			$dim_names[$dim->getId()] = array("name" => clean($dim->getName()));
		}
		ajx_extra_data(array("dim_names" => $dim_names));
	}
	
	//return all childs of a member
	function get_member_childs() {
		$mem_id = array_var($_GET, 'member');
		$mem = Members::getMemberById($mem_id);
		if($mem instanceof Member){
			//Do not use contact member cache for superadmins
			if(!logged_user()->isAdministrator()){
				//use the contact member cache
				$dimension = $mem->getDimension();
				$params = array(
						"dimension" => $dimension,
						"contact_id" => logged_user()->getId(),
						"parent_member_id" => $mem->getId()
				);
				$childs = $member_cache_list = ContactMemberCaches::getAllMembersWithCachedParentId($params);	
			}else{
				$childs = Members::getSubmembers($mem, false, "");
			}
						
			$members = $this->buildMemberList($childs, $mem->getDimension(),  null, null, null, null);
			
			ajx_extra_data(array("members" => $members, "dimension" => $mem->getDimensionId()));			
		}
		ajx_current("empty");
	}
	
	//return all parents of a member
	function get_member_parents() {
		$mem_id = array_var($_GET, 'member');
		$mem = Members::getMemberById($mem_id);
		if($mem instanceof Member){
			$parents = $mem->getAllParentMembersInHierarchy(true);
			
			$members = $this->buildMemberList($parents, $mem->getDimension(),  null, null, null, null);
			
			ajx_extra_data(array("member_id" => $mem_id));
			ajx_extra_data(array("members" => $members));
			ajx_extra_data(array('dimension_id' => $mem->getDimensionId()));
		}
		ajx_current("empty");
	}
	
	function buildMemberList($all_members, $dimension,  $allowed_member_type_ids, $allowed_object_type_ids, $item_object, $object_type_id, $return_only_name=false) {
		$dot_array = array(); // Dimension Object Types array (cache)
		$membersset = array();
		foreach ($all_members as $m) {
			$membersset[$m->getId()] = true;
		}
		$members = array();
		foreach ($all_members as $m) {
			/* @var  $m Member */
			if ($m->getArchivedById() > 0) continue;
			if ($object_type_id != null){
				$selectable = in_array($m->getObjectTypeId(), $allowed_object_type_ids) ? true : false;
				if ($selectable && isset($item_object)) {
					if (! $item_object->canAdd(logged_user(), array($m)) ) continue;
				}
			}else{
				$selectable = true ;
			}
			if ( count($allowed_member_type_ids) && !in_array($m->getObjectTypeId(), $allowed_member_type_ids) ) {
				continue;	
			}
			$tempParent = $m->getParentMemberId();
			
			//check if have parent member id from Contact Member Cache
			if(isset($m->cached_parent_member_id)){
				$tempParent = $m->cached_parent_member_id;
			}else{
				if(!logged_user()->isAdministrator()){
					$x = $m;
					while ($x instanceof Member && !isset($membersset[$tempParent])) {
						$tempParent = $x->getParentMemberId();
						if ($x->getParentMemberId() == 0) break;
						$x = $x->getParentMember();
					}
					if (!$x instanceof Member) {
						$tempParent = 0;
					}
				}else{
					$tempParent = $m->getParentMemberId();
				}
				
			}
			
			$memberOptions = '';
			
			// SET member options (dimension object types table)
			// CHeck dot cache, if not set goto database and add to cache
			if ( empty($dot_array[$dimension->getId()]) || empty ($dot_array[$dimension->getId()][$m->getObjectTypeId()]) ) {
				$dot = DimensionObjectTypes::instance()->findOne(array("conditions" =>"dimension_id = ".$dimension->getId() ." AND object_type_id = ".$m->getObjectTypeId()));
				if ($dot instanceof DimensionObjectType){
					if (empty($dot_array['dimension_id'])) {
						$dot_array[$dimension->getId()] = array();
					}
					$dot_array[$dimension->getId()][$m->getObjectTypeId()] = $dot;
				}
			}
			if ( !empty($dot_array[$dimension->getId()]) || ($dot_array[$dimension->getId()][$m->getObjectTypeId()]) instanceof DimensionObjectType ) {
				$dot =  $dot_array[$dimension->getId()][$m->getObjectTypeId()];
				$memberOptions = $dot->getOptions(true);
			}
			
			if ($return_only_name) {
				$path = trim($m->getPath());
				$member = array(
					"id" => $m->getId(),
					"name" => $m->getName(),
					"path" => $path,
					"to_show" => $m->getName() . ($path != "" ? " ($path)" : ""),
					"dim" => $m->getDimensionId(),
					"ico" => $m->getIconClass(),
				);
			} else {
				//Do not use contact member cache for superadmins
				if(!logged_user()->isAdministrator()){
					//check childs from contact member cache
					$childsIds = ContactMemberCaches::getAllChildrenIdsFromCache(logged_user()->getId(), $m->getId());
				}else{
					$childsIds = $m->getAllChildrenIds(false,null,"");
				}				
				$totalChilds = count($childsIds);
				$haveChilds = ($totalChilds > 0)? true : false; 
				
				/* @var $m Member */
				$additional_member_class = "";
				Hook::fire('additional_member_node_class', $m, $additional_member_class);
				$member = array(
					"id" => $m->getId(),
					"color" => $m->getMemberColor(),
					"name" => clean($m->getName()),
					"parent" => $tempParent,
					"realParent" => $m->getParentMemberId(),
					"object_id" => $m->getObjectId(),
					"options"  => $memberOptions,
					"depth" => $m->getDepth(),
					"cls" => $additional_member_class,
					"iconCls" => $m->getIconClass(),
					"selectable" => isset($selectable) ? $selectable : false,
					"dimension_id" => $m->getDimensionId(),
					"object_type_id" => $m->getObjectTypeId(),
					"expandable" => $haveChilds,
					"realTotalChilds" => $totalChilds,
					"allow_childs" => $m->allowChilds()
				);
				// Member Actions
				if (can_manage_dimension_members(logged_user())){
					$editUrl = '';
					// If member has an object linked, take object edit url
					
					if ($ot = ObjectTypes::findById($m->getObjectTypeId())) {
						if ($handler = $ot->getHandlerClass() ){
							eval ("\$itemClass = $handler::instance()->getItemClass();");
							if ($itemClass) {
								$instance = new $itemClass();
								$instance->setId($m->getObjectId());
								$instance->setObjectId($m->getObjectId());
								if ($instance instanceof Contact) {
									if ($ot->getName() == 'company') $instance->setIsCompany(1);
								}
								$editUrl = $instance->getEditUrl();
							}
						}
					}
					
					// Take default membewr edit url if not overwitten
					if (!$editUrl) {
						$editUrl = get_url('member', 'edit', array('id'=> $m->getId()));
					}
					$member['actions'] = array(array(
						'url' => $editUrl,
			  			'text' => '',
			  			'iconCls' => 'ico-edit',
			  			'class' => 'action-edit'
					));	
				}
			}
			$members[] = $member;
		}
		
		// re-sort by parent and name
		$tmp_members = array();
		foreach ($members as $m) {
			$tmp_members[str_pad(array_var($m, 'parent'), 20, "0", STR_PAD_LEFT) . strtolower(array_var($m, 'name')) . array_var($m, 'id')] = $m;
		}
		ksort($tmp_members, SORT_STRING);
		$members = $tmp_members;		
		return $members ;
	}
	
	
	
	function linked_object_filters() {
		$genid = gen_id();
		$html = "<div class='linked-objects-member-filters'>";
		
		$context = active_context();
		
		$dimensions = Dimensions::findAll(array('conditions' => 'is_manageable = 1'));
		
		foreach ($dimensions as $dimension) {
			
			$dimension_id = $dimension->getId();
			$sel_name = "";
			$sel_id = 0;
			foreach ($context as $selection) {
				if ($selection instanceof Member && $selection->getDimensionId() == $dimension_id) {
					$sel_name = clean($selection->getName());
					$sel_id = $selection->getId();
				}
			}
			
			$html .= '<div class="lo-member-selector"><div class="selector-label">'.lang('filter by '.$dimension->getCode()).'</div>';
			
			$autocomplete_options = array();
			$dim_controller = new DimensionController();
			$members = $dim_controller->initial_list_dimension_members($dimension_id, null, null, false, "", null, false, null, true, array());
			foreach ($members as $m) {
				$autocomplete_options[] = array($m['id'], $m['name'], $m['path'], $m['to_show'], $m['ico'], $m['dim']);
			}
			
			$combo_listeners = array(
				"select" => "function (combo, record, index) { Ext.getCmp('dimFilter').fireEvent('memberselected', record.data); }",
			);
			$html .= autocomplete_member_combo("member_autocomplete-dim".$dimension_id, $dimension_id, $autocomplete_options, 
					lang($dimension->getCode()), array('class' => 'member-name-input', 'selected_name' => $sel_name), false, $genid .'add-member-input-dim'. $dimension_id, $combo_listeners);
			$html .= "</div>";
			
			if ($sel_id > 0) {
				$html .= "<script>Ext.getCmp('obj_picker_grid').member_filter[$dimension_id] = $sel_id;</script>";
			}
		}
		
		$html .= '<div class="buttons"><button onclick="Ext.getCmp(\'dimFilter\').fireEvent(\'clearfilters\', \''.$genid.'\');">'.lang('remove all filters').'</button></div>';
		$html .= '</div>';
		
		die($html);
	}
}
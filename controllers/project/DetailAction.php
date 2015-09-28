<?php

class DetailAction extends CAction
{
	/**
	* Dashboard Organization
	*/
    public function run($id) { 
    	$controller=$this->getController();
		
		$project = Project::getPublicData($id);
	
	  	$controller->sidebar1 = array(
	      array('label' => "ACCUEIL", "key"=>"home","iconClass"=>"fa fa-home","href"=>"communecter/project/dashboard/id/".$id),
	    );
	
	    $controller->title = (isset($project["name"])) ? $project["name"] : "";

		$roomCount = PHDB::count(ActionRoom::COLLECTION, array("parentType"=>Project::COLLECTION , "parentId"=>$id));
	    $controller->toolbarMBZ = array(
	    	array('tooltip' => "TIMELINE : Project Activity","iconClass"=>"fa fa-rss","href"=>"<a  class='tooltips btn btn-default' href='".Yii::app()->createUrl("/".$controller->module->id."/news/index/type/projects/id/".$id)."'"),
	    	array('tooltip' => "See Project Discussion", "badge"=>"<span class='notifications-count  badge badge-danger animated bounceIn'>".$roomCount."</span>","iconClass"=>"fa fa-comments-o","href"=>"<a class='tooltips btn btn-default' href='".Yii::app()->createUrl("/".$controller->module->id."/rooms/index/type/projects/id/".$id)."'")
	    );
	    
	    $controller->subTitle = ( isset($project["description"])) ? ( ( strlen( $project["description"] ) > 120 ) ? substr($project["description"], 0, 120)."..." : $project["description"]) : "";
	    $controller->pageTitle = "Communecter - Informations sur le projet ".$controller->title;
	  	$organizations = array();
	  	$people = array();
	  	$contributors =array();
	  	$properties = array();
	  	$tasks = array();
	  	$contentKeyBase = $controller->id.".".$controller->action->id; 
	  	$images = Document::getListDocumentsURLByContentKey($id, $contentKeyBase, Document::DOC_TYPE_IMAGE);

	  	if(!empty($project)){
	  		$params = array();
	  		// Get people or orga who contribute to the project 
	  		// Get image for each contributors
	  		if(isset($project["links"])){
	  			foreach ($project["links"]["contributors"] as $uid => $e) {
	  				if($e["type"]== Organization::COLLECTION){
	  					$organization = Organization::getPublicData($uid);
	  					if (!empty($organization)) {
	  						array_push($organizations, $organization);
	  						$organization["type"]="organization";
	  						$profil = Document::getLastImageByKey($uid, Organization::COLLECTION, Document::IMG_PROFIL);
	  						if($profil !="")
								$organization["imagePath"]= $profil;
	  						array_push($contributors, $organization);
	  					}
	  				}else if($e["type"]== Person::COLLECTION){
	  					$citoyen = Person::getPublicData($uid);
	  					if(!empty($citoyen)){
	  						array_push($people, $citoyen);
	  						$citoyen["type"]="citoyen";
	  						$profil = Document::getLastImageByKey($uid, Person::COLLECTION, Document::IMG_PROFIL);
	  						if($profil !="")
								$citoyen["imagePath"]= $profil;
	  						array_push($contributors, $citoyen);
	  						if( $uid == Yii::app()->session['userId'] )
                    			array_push($controller->toolbarMBZ, array('tooltip' => "Send a message to this Project","iconClass"=>"fa fa-envelope-o","href"=>"<a href='#' class='new-news tooltips btn btn-default' data-id='".$id."' data-type='".Project::COLLECTION."' data-name='".$project['name']."'") );
	  					}
	  				}
	  			}
	  		}
	  		
	  		$events=array();
	  		if( isset($project["links"]["events"])) {
	    		foreach ($project["links"]["events"] as $key => $event) {
	    			$event = Event::getById( $key );
	            	if (!empty($event)) {
		            	array_push($events, $event);
		            }
		    	}
		    }

	  		// Properties defines the chart of the Project
	  		if (isset($project["properties"]["chart"])){
		  		$properties=$project["properties"]["chart"];
	  		}
	  		//Tasks will provide the GANTT of the project
	  		if (isset($project["tasks"])){
		  		$tasks=$project["tasks"];
	  		}
	  	}
	  	if(isset($project["_id"]) && isset(Yii::app()->session["userId"]) && Link::isLinked($project["_id"] , Project::COLLECTION , Yii::app()->session['userId']))
			$htmlFollowBtn = array('tooltip' => "stop contributing to this Project", "parent"=>"span","parentId"=>"linkBtns","iconClass"=>"disconnectBtnIcon fa fa-unlink","href"=>"<a href='javascript:;' class='disconnectBtn text-red tooltips btn btn-default' data-name='".$project["name"]."' data-id='".$project["_id"]."' data-type='".Project::COLLECTION."' data-member-id='".Yii::app()->session["userId"]."' data-ownerlink='".Link::person2projects."' data-targetlink='".Link::project2person."'");
		else
			$htmlFollowBtn = array('tooltip' => "I want to contribute to this Project", "parent"=>"span","parentId"=>"linkBtns","iconClass"=>"connectBtnIcon fa fa-unlink","href"=>"<a href='javascript:;' class='connectBtn tooltips btn btn-default' id='addKnowsRelation' data-ownerlink='".Link::person2projects."' data-targetlink='".Link::project2person."'");
	  	array_push($controller->toolbarMBZ, $htmlFollowBtn);
	  	//Gestion de l'admin - true or false
	  	// First find if user session is directly link to project
	  	// Second if not, find if user belong to an organization admin of the project
	  	// return true or false
	  	$isProjectAdmin = false;
	  	$admins=[];
    	if(isset($project["_id"]) && isset(Yii::app()->session["userId"])) {
    		$isProjectAdmin =  Authorisation::isProjectAdmin((String) $project["_id"],Yii::app()->session["userId"]);
    		if (!$isProjectAdmin && !empty($organizations)){
	    		foreach ($organizations as $data){
		    		$admins = Organization::getMembersByOrganizationId( (string)$data['_id'], Person::COLLECTION , "isAdmin" );
		    		foreach ($admins as $key => $member){
			    		if ($key == Yii::app()->session["userId"]){
				    		$isProjectAdmin=1;
				    		break 2;
			    		}
		    		}
	    		}
    		}
		}

	  	$lists = Lists::get(array("organisationTypes"));
	  	$params["countries"] = OpenData::getCountriesList();
	  	$params["tags"] = Tags::getActiveTags();
		$params["organizationTypes"] = $lists["organisationTypes"];
	  	$params["images"] = $images;
	  	$params["contentKeyBase"] = $contentKeyBase;
	  	$params["contributors"] = $contributors;
	  	$params["project"] = $project;
	  	$params["organizations"] = $organizations;
	  	$params["events"] = $events;
	  	$params["people"] = $people;
	  	$params["properties"] = $properties;
	  	$params["tasks"]=$tasks;

	  	$params["admin"]=$isProjectAdmin;
	  	$params["admins"]=$admins;

		

		$page = "detail";
		if(Yii::app()->request->isAjaxRequest)
            echo $controller->renderPartial($page,$params,true);
        else 
			$controller->render( $page , $params );
    }
}

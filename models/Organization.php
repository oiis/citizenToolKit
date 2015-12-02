<?php 
class Organization {

	const COLLECTION = "organizations";
	const CONTROLLER = "organization";
	const ICON = "fa-users";
	const ICON_BIZ = "fa-industry";
	const ICON_GROUP = "fa-circle-o";
	const ICON_GOV = "fa-circle-o";

	const TYPE_NGO = "NGO";
	const TYPE_BUSINESS = "LocalBusiness";
	const TYPE_GROUP = "Group";
	const TYPE_GOV = "GovernmentOrganization";

	
	//From Post/Form name to database field name
	private static $dataBinding = array(
	    "name" => array("name" => "name", "rules" => array("required", "organizationSameName")),
	    "email" => array("name" => "email", "rules" => array("email")),
	    "created" => array("name" => "created"),
	    "creator" => array("name" => "creator"),
	    "type" => array("name" => "type"),
	    "shortDescription" => array("name" => "shortDescription"),
	    "description" => array("name" => "description"),
	    "address" => array("name" => "address"),
	    "streetAddress" => array("name" => "address.streetAddress"),
	    "postalCode" => array("name" => "address.postalCode"),
	    "city" => array("name" => "address.codeInsee"),
	    "addressLocality" => array("name" => "address.addressLocality"),
	    "addressCountry" => array("name" => "address.addressCountry"),
	    "geo" => array("name" => "geo"),
	    "tags" => array("name" => "tags"),
	    "typeIntervention" => array("name" => "typeIntervention"),
	    "typeOfPublic" => array("name" => "typeOfPublic"),
	    "url"=>array("name" => "url"),
	    "telephone" => array("name" => "telephone"),
	    "video" => array("name" => "video")
	);
	
	//See findOrganizationByCriterias...
	public static function getWhere($params) {
	  	return PHDB::find( self::COLLECTION,$params);
	}

	//TODO SBAR - First test to validate data. Move it to DataValidator
  	private static function getCollectionFieldNameAndValidate($organizationFieldName, $organizationFieldValue) {
		$res = "";
		if (isset(self::$dataBinding["$organizationFieldName"])) {
			$data = self::$dataBinding["$organizationFieldName"];
			$name = $data["name"];
			//Validate field
			if (isset($data["rules"])) {
				$rules = $data["rules"];
				foreach ($rules as $rule) {
					$isDataValidated = DataValidator::$rule($organizationFieldValue);
					if ($isDataValidated != "") {
						throw new CTKException($isDataValidated);
					}
				}	
			}
		} else {
			throw new CTKException("Unknown field :".$organizationFieldName);
		}
		return $name;
	}

	/**
	 * insert a new organization in database
	 * @param array A well format organization 
	 * @param String $creatorId : an existing user id representing the creator of the organization
	 * @param String $adminId : can be ommited. user id representing the administrator of the organization
	 * @return array result as an array. 
	 */
	public static function insert($organization, $creatorId, $adminId = null) {
	    $newOrganization = Organization::getAndCheckOrganization($organization);
		
		//Manage tags : save any inexistant tag to DB 
		if (isset($newOrganization["tags"]))
			$newOrganization["tags"] = Tags::filterAndSaveNewTags($newOrganization["tags"]);

		//Add the user creator of the organization in the system
		if (empty($creatorId)) {
			throw new CTKException("The creator of the organization is required.");
		} else {
			$newOrganization["creator"] = $creatorId;	
		}
	
		//Insert the organization
	    PHDB::insert( Organization::COLLECTION, $newOrganization);
		
	    if (isset($newOrganization["_id"])) {
	    	$newOrganizationId = (String) $newOrganization["_id"];
	    } else {
	    	throw new CTKException(Yii::t("organization","Problem inserting the new organization"));
	    }
		
		//Manage link with the creator depending of the role selected
		if (@$organization["role"] == "admin") {
			$isToLink = true;
			$memberId = $creatorId;
			$isAdmin = true;
		} else if (@$organization["role"] == "member") {
			$isToLink = true;
			$memberId = $creatorId;
			$isAdmin = false;
		} else if (@$organization["role"] == "creator") {
			$isToLink = false;
		}
		unset($organization["role"]);

		//If the adminId is set then add him as admin
		if ($adminId) {
			$isToLink = true;
			$memberId = $adminId;
			$isAdmin = true;
		}
		if ($isToLink) {
		    Link::addMember($newOrganizationId, Organization::COLLECTION, $memberId, Person::COLLECTION, $creatorId, $isAdmin);
		}

	    //send Notification Email
	    $creator = Person::getById($creatorId);
	    //Mail::newOrganization($creator,$newOrganization);
	    
		Notification::createdObjectAsParam(Person::COLLECTION,$creatorId,Organization::COLLECTION, $newOrganizationId, null, null, $newOrganization["geo"],
											$newOrganization["tags"],$newOrganization["address"]["codeInsee"]);

	    $newOrganization = Organization::getById($newOrganizationId);
	    return array("result"=>true,
		    			"msg"=>"Votre organisation est communectée.", 
		    			"id"=>$newOrganizationId, 
		    			"newOrganization"=> $newOrganization);
	}
	
	public static function newOrganizationFromPost($organization) {
		$newOrganization = array();
		$newOrganization["email"] = empty($organization['organizationEmail']) ? "" : $organization['organizationEmail'];
		$newOrganization["country"] = empty($organization['organizationCountry']) ? "" : $organization['organizationCountry'];
		$newOrganization["name"] = empty($organization['organizationName']) ? "" : $organization['organizationName'];
		$newOrganization["type"] = empty($organization['type']) ? "" : $organization['type'];
		//Location
		$newOrganization["streetAddress"] = empty($organization['streetAddress']) ? "" : $organization['streetAddress'];
		$newOrganization["postalCode"] = empty($organization['postalCode']) ? "" : $organization['postalCode'];
		$newOrganization["city"] = empty($organization['city']) ? "" : $organization['city'];
		$newOrganization["addressCountry"] = empty($organization['organizationCountry']) ? "" : $organization['organizationCountry'];

		$newOrganization["description"] = empty($organization['description']) ? "" : $organization['description'];
		$newOrganization["tags"] = empty($organization['tagsOrganization']) ? "" : $organization['tagsOrganization'];
		$newOrganization["typeIntervention"] = empty($organization['typeIntervention']) ? "" : $organization['typeIntervention'];
		$newOrganization["typeOfPublic"] = empty($organization['public']) ? "" : $organization['public'];
		$newOrganization["role"] = empty($organization['role']) ? "" : $organization['role'];
		$newOrganization["category"] = empty($organization['category']) ? "" : $organization['category'];

		//error_log("latitude : ".$organization['geoPosLatitude']);
		if(!empty($organization['geoPosLatitude']) && !empty($organization["geoPosLongitude"])){
			$newOrganization["geo"] = array("@type"=>"GeoCoordinates",
											"latitude" => $organization['geoPosLatitude'],
											"longitude" => $organization['geoPosLongitude']);

			$newOrganization["geoPosition"] = array("type"=>"point",
													"coordinates" =>
													array($organization['geoPosLatitude'],
											 	  		  $organization['geoPosLongitude'])
											 	  	);
			//$newOrganization["geo"] = empty($organization['public']) ? "" : $organization['public'];
		}
		
		return $newOrganization;
	}


	public static function newOrganizationFromImportData($organization) {
		
		$newOrganization = array();
		$newOrganization["key"] = "organizationsCollection";
		$newOrganization["email"] = empty($organization['email']) ? "" : $organization['email'];
		$newOrganization["name"] = empty($organization['name']) ? "" : $organization['name'];
		$newOrganization["type"] = empty($organization['type']) ? Organization::TYPE_GROUP : $organization['type'];
		$newOrganization["postalCode"] = empty($organization['postalCode']) ? "" : $organization['postalCode'];
		$newOrganization["city"] = empty($organization['city']) ? "" : $organization['city'];
		$newOrganization["description"] = empty($organization['description']) ? "" : $organization['description'];
		$newOrganization["tags"] = empty($organization['tags']) ? "" : $organization['tags'];
		$newOrganization["roles"] = empty($organization['roles']) ? "" : $organization['roles'];
		$newOrganization["video"] = empty($organization['video']) ? "" : $organization['video'];
		$newOrganization["contactPoint"] = empty($organization['contactPoint']) ? "" : $organization['contactPoint'];
		//$newOrganization["address"] = empty($organization['address']) ? "" : $organization['address'];
		$newOrganization["created"] = empty($organization['created']) ? "" : $organization['created'];
		$newOrganization["details"] = empty($organization['details']) ? "" : $organization['details'];
		$newOrganization['address']['streetAddress'] = empty($organization['address']['streetAddress']) ? "" : $organization['address']['streetAddress'];
		$newOrganization['address']['postalCode'] = empty($organization['address']['postalCode']) ? "" : $organization['address']['postalCode'];
		$newOrganization['address']['addressCountry'] = empty($organization['address']['addressCountry']) ? "" : $organization['address']['addressCountry'];
		$newOrganization['address']['addressLocality'] = empty($organization['address']['addressLocality']) ? "" : $organization['address']['addressLocality'];
		$newOrganization['address']['geo']['latitude'] = empty($organization['address']['geo']['latitude']) ? "" : $organization['address']['geo']['latitude'];
		$newOrganization['address']['geo']['longitude'] = empty($organization['address']['geo']['longitude']) ? "" : $organization['address']['geo']['longitude'];
		$newOrganization["url"] = empty($organization['url']) ? "" : $organization['url'];
		/*if(!empty($organization['address']['streetAddress']))
		{
			$nominatim = "http://nominatim.openstreetmap.org/search?q=".urlencode($organization['address']['streetAddress'])."&format=json&polygon=0&addressdetails=1";

			$curl = curl_init($nominatim);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$returnCURL = json_decode(curl_exec($curl),true);
			//var_dump($returnCURL);
			if(!empty($returnCURL) || $returnCURL != array())
			{
				foreach ($returnCURL as $key => $valueAdress) {
					$newOrganization['address']['geo']['@type'] = "GeoCoordinates" ;
					$newOrganization['address']['geo']['latitude'] = $valueAdress['lat'];
					$newOrganization['address']['geo']['longitude'] = $valueAdress['lon'] ;
				}

			}	
			curl_close($curl);
		}*/

		return $newOrganization;
	}

	/**
	 * Apply organization checks and business rules before inserting
	 * @param array $organization : array with the data of the organization to check
	 * @return array Organization well format : ready to be inserted
	 */
	public static function getAndCheckOrganization($organization) {
		if (empty($organization['name'])) {
			throw new CTKException(Yii::t("organization","You have to fill a name for your organization"));
		}
		
		// Is There a association with the same name ?
	    $organizationSameName = PHDB::findOne( Organization::COLLECTION,array( "name" => $organization["name"]));      
	    if($organizationSameName) { 
	      throw new CTKException(Yii::t("organization","An organization with the same name already exist in the plateform"));
	    }

		$newOrganization = array(
			"name" => $organization['name'],
			'created' => time()
		);
		
		//email : mandotory 
		if(!empty($organization['email'])) {
			//validate Email
			if (! preg_match('#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#',$organization['email'])) { 
				throw new CTKException("Vous devez remplir un email valide.");
			}
			$newOrganization["email"] = $organization['email'];
		}

		if (empty($organization['type'])) {
			throw new CTKException(Yii::t("organization", "You have to fill the type of your organization"));
		}
		$newOrganization["type"] = $organization['type'];
				  
		if(!empty($organization['postalCode'])) {
			if (!empty($organization['city'])) {
				$insee = $organization['city'];
				$address = SIG::getAdressSchemaLikeByCodeInsee($insee);
				$address["streetAddress"] = $organization["streetAddress"];
				$address["addressCountry"] = $organization["addressCountry"];
				$newOrganization["address"] = $address;

				if(empty($organization["geo"]))
					$newOrganization["geo"] = SIG::getGeoPositionByInseeCode($insee);
				else
					$newOrganization["geo"] = $organization["geo"];
			}
		}

		//méthode pour récupérer le code insee à partir d'une position geographique :
		//$geo = SIG::getPositionByCp($organization['postalCode']);
		//$insee = SIG::getInseeByLatLngCp($geo["latitude"], $geo["longitude"], $organization['postalCode']);
				  
		if (!empty($organization['description']))
			$newOrganization["description"] = $organization['description'];
				  
		//Tags
		if (isset($organization['tags'])) {
			if ( gettype($organization['tags']) == "array" ) {
				$tags = $organization['tags'];
			} else if ( gettype($organization['tags']) == "string" ) {
				$tags = explode(",", $organization['tags']);
			}
			$newOrganization["tags"] = $tags;
		}
		
		//category
		if (isset($organization['category'])) {
			if ( gettype($organization['category']) == "array" ) {
				$category = $organization['category'];
			} else if ( gettype($organization['category']) == "string" ) {
				$category = explode(",", $organization['category']);
			}
			$newOrganization["category"] = $category;
		}

		//************************ Import Data specific ********************/
		//ConctactPoint
		if(!empty($organization['contactPoint'])){
			foreach ($organization['contactPoint'] as $key => $valueContactPoint) {
				if(!empty($valueContactPoint['email'])){
					//validate Email
					if (! preg_match('#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#',$valueContactPoint['email'])) { 
						throw new CTKException("Vous devez remplir un email valide pour le contactPoint ".$valueContactPoint['email'].".");
					}
				}
			}
			$newOrganization["contactPoint"] = $organization['contactPoint'];
		}

		//address by ImportData
		if(!empty($organization['address'])){
			$newOrganization["address"] = $organization['address'];
		}

		//details by ImportData
		if(!empty($organization['details'])){
			$newOrganization["details"] = $organization['details'];
		}

		//url by ImportData
		if(!empty($organization['url'])){
			$newOrganization["url"] = $organization['url'];
		}


		//************************ Spécifique Granddir ********************/
		//TODO SBAR : A sortir du CTK. Prévoir une méthode populateSpecific() à appeler ici
		//Cette méthode sera implémenté dans le Modèle Organization spécifique de Granddir
		//Type of Intervention
		if (!empty($organization["typeIntervention"])) {
			$newOrganization["typeIntervention"] = $organization["typeIntervention"];
		}
	
		//Type of Intervention
		if (!empty($organization["typeOfPublic"])) {
			$newOrganization["typeOfPublic"] = $organization["typeOfPublic"];
		}

		return $newOrganization;
	}

	/**
	 * get an Organisation By Id
	 * @param type $id : is the mongoId of the organisation
	 * @return type
	 */
	public static function getById($id) {

	  	$organization = PHDB::findOne(Organization::COLLECTION,array("_id"=>new MongoId($id)));
	  	
	  	if (empty($organization)) {
            //TODO Sylvain - Find a way to manage inconsistent data
            //throw new CommunecterException("The organization id ".$id." is unkown : contact your admin");
        } else {
			$organization = array_merge($organization, Document::retrieveAllImagesUrl($id, self::COLLECTION));
			$organization["typeSig"] = "organizations";
        }
	  	return $organization;
	}

	/**
	 * Retrieve a simple organization (id, name, profilImageUrl) by id from DB
	 * @param String $id of the organization
	 * @return array with data id, name, profilImageUrl, logoImageUrl
	 */
	public static function getSimpleOrganizationById($id) {

		$simpleOrganization = array();
		$orga = PHDB::findOneById( self::COLLECTION ,$id, array("id" => 1, "name" => 1, "type" => 1, "email" => 1, "address" => 1, "pending" => 1) );

		$simpleOrganization["id"] = $id;
		$simpleOrganization["name"] = @$orga["name"];
		$simpleOrganization["type"] = @$orga["type"];
		$simpleOrganization["email"] = @$orga["email"];
		$simpleOrganization["pending"] = @$orga["pending"];
		$simpleOrganization = array_merge($simpleOrganization, Document::retrieveAllImagesUrl($id, self::COLLECTION, @$orga["type"]));
		
		$logo = Document::getLastImageByKey($id, self::COLLECTION, Document::IMG_LOGO);
		$simpleOrganization["logoImageUrl"] = $logo;
		
		$simpleOrganization["address"] = empty($orga["address"]) ? array("addressLocality" => "Unknown") : $orga["address"];
		
		return $simpleOrganization;
	}

	/**
	 * get members an Organization By an organization Id
	 * @param String $id : is the mongoId (String) of the organization
	 * @param String $type : can be use to filter the member by type (all (default), person, organization)
	 * @param String $role : can be use to filter the member by role (isAdmin:true)
	 * @return arrays of members (links.members)
	 */
	public static function getMembersByOrganizationId($id, $type="all",$role=null) {
	  	$res = array();
	  	$organization = Organization::getById($id);
	  	
	  	if (empty($organization)) {
            throw new CTKException(Yii::t("organization", "The organization id is unkown : contact your admin"));
        }
	  	if (isset($organization) && isset($organization["links"]) && isset($organization["links"]["members"])) {
	  		$members = $organization["links"]["members"];
	  		//No filter needed
	  		if ($type == "all") {
	  			return $members;
	  		} else {
	  			foreach ($organization["links"]["members"] as $key => $member) {
		            if ($member['type'] == $type ) {
		                $res[$key] = $member;
		            }
		            if ( $role && @$member[$role] == true ) {
		                $res[$key] = $member;
		            }
	        	}
	  		}
	  	}
	  	return $res;
	}

	/**
	 * update an organization in database
	 * @param String $organizationId : 
	 * @param array $organization organization fields to update
	 * @param String $userId : the userId making the update
	 * @return array of result (result => boolean, msg => string)
	 */
	public static function update($organizationId, $organization, $userId) 
	{
		//Check if user is authorized to update
		if (! Authorisation::isOrganizationAdmin($userId, $organizationId)) {
			return Rest::json(array("result"=>false, "msg"=>Yii::t("organization", "Unauthorized Access.")));
		}

		foreach ($organization as $fieldName => $fieldValue) {
			self::updateField($organizationId, $fieldName, $fieldValue);
		}

	    return array("result"=>true, "msg"=>Yii::t("organization", "The organization has been updated"), "id"=>$organizationId);
	}
	
	/**
	 * Happens when an Organisation is invited or linked as a member and doesn't exist in the system
	 * It is created in a temporary state
	 * This creates and invites the email to fill extra information 
	 * into the Organisation profile 
	 * @param array $param minimal information in order to create the organization
	 * @return type
	 */
	public static function createAndInvite($param) {
	  	try {
	  		$res = self::insert($param, $param["invitedBy"], $param["invitedBy"]);
	  	} catch (CTKException $e) {
	  		$res = array("result"=>false, "msg"=> $e->getMessage());
	  	}
        //TODO TIB : mail Notification 
        //for the organisation owner to subscribe to the network 
        //and complete the Organisation Profile
        
        return $res;
	}

	/**
	 * Get an organization from an id and return filter data in order to return only public data
	 * @param type $id 
	 * @return organization structure
	 */
	public static function getPublicData($id) {
		//Public datas 
		$publicData = array (
			"imagePath",
			"name",
			"city",
			"socialAccounts",
			"url",
			"coi"
		);

		//TODO SBAR = filter data to retrieve only public data	
		$organization = Organization::getById($id);
		if (empty($organization)) {
			//throw new CTKException("The organization id is unknown ! Check your URL");
		}

		return $organization;
	}

	/**
	 * When an initation to join an organization network is sent :
	 * this method will :
	 * 1. Create a new person and organization.
	 * 2. Make the new person a member and admin of the organization
	 * 3. Join the network of the organization inviting
	 * @param type $person the minimal data to create a person
	 * @param type $organization the minimal data to create an organization
	 * @param type $parentOrganizationId the organization Id to join the network of
	 * @return newPersonId ans newOrganizationId
	 */
	public static function createPersonOrganizationAndAddMember($person, $organization, $parentOrganizationId) {
		//The data check is normaly done before inserting but the both data (organization and person)  
		//must be ok before inserting
		//Check person datas 
		Person::getAndcheckPersonData($person, false);
		//Check organization datas 
		Organization::getAndCheckOrganization($organization);
		
		//Create a new person + send email validation
		$res = Person::insert($person);
		Mail::validatePerson($res["person"]);

		//Create a new organization
		$newOrganization = Organization::insert($organization, $newPerson["id"], $newPerson["id"]);

		//Link the person as an admin
		Link::addMember($newOrganization["id"], Organization::COLLECTION, $newPerson["id"], Person::COLLECTION, $newPerson["id"], true);

		//Link the organization as a member of the invitor
		//Is the parent oragnization can manage the organizations bellow ?
		$isParentOrganizationAdmin = @Yii::app()->params['isParentOrganizationAdmin'];
		Link::addMember($parentOrganizationId, Organization::COLLECTION, $newOrganization["id"], Organization::COLLECTION, 
						$newPerson["id"], $isParentOrganizationAdmin);
		
		return array("result"=>true, "msg"=>Yii::t("organization", "The invitation process completed with success"), "id"=>$newOrganization["id"]);;
	}


	/**
	 * List all the event of an organization and his members (if can edit member)
	 * @param String $organisationId : is the mongoId of the organisation
	 * @return all the event link with the organization
	 */
	//TODO SBAR : Refactor using a startDate in order to not retrieve all the database
	public static function listEventsPublicAgenda($organizationId){
		$events = array();
		$organization = Organization::getById($organizationId);
		
		if(isset($organization["links"]["events"])){
			foreach ($organization["links"]["events"] as $keyEv => $valueEv) {
				 $event = Event::getPublicData($keyEv);
           		 $events[$keyEv] = $event;
			}
		}
		//Specific case : if canEditMember
		if(Authorisation::canEditMembersData($organizationId)){
			$subOrganization = Organization::getMembersByOrganizationId($organizationId, Organization::COLLECTION);
			foreach ($subOrganization as $key => $value) {
				 $newOrganization = Organization::getById($key);
				 if(!empty($newOrganization)&& isset($newOrganization["links"]["events"])){
				 	foreach ($newOrganization["links"]["events"] as $keyEv => $valueEv) {
				 		$event = Event::getPublicData($keyEv);
           		 		$events[$keyEv] = $event;
				 	}
				 }	 
			}
		}
		foreach ($events as $key => $value) {
        	$profil = Document::getLastImageByKey($key, PHType::TYPE_EVENTS, Document::IMG_PROFIL);
        	if($profil!="")
        		$value['imagePath']=$profil;
        }
		return $events;
	}
	/**
	 * List all the project of an organization and his members (if can edit member)
	 * @param String $organisationId : is the mongoId of the organisation
	 * @return all the project link with the organization
	 */

	public static function listProjects($organizationId){
		$projects = array();
		$organization = Organization::getById($organizationId);
		
		if(isset($organization["links"]["projects"])){
			foreach ($organization["links"]["projects"] as $keyProj => $valueProj) {
				 $project = Project::getPublicData($keyProj);
           		 $projects[$keyProj] = $project;
			}
		}
		//Specific case : if canEditMember
		if(Authorisation::canEditMembersData($organizationId)){
			$subOrganization = Organization::getMembersByOrganizationId($organizationId, Organization::COLLECTION);
			foreach ($subOrganization as $key => $value) {
				 $newOrganization = Organization::getById($key);
				 if(!empty($newOrganization)&& isset($newOrganization["links"]["projects"])){
				 	foreach ($newOrganization["links"]["projects"] as $keyProj => $valueProj) {
				 		$project = Project::getPublicData($keyProj);
           		 		$projects[$keyProj] = $project;
				 	}
				 }	 
			}
		}
		foreach ($projects as $key => $value) {
        	$profil = Document::getLastImageByKey($key, PHType::TYPE_PROJECTS, Document::IMG_PROFIL);
        	if($profil!="")
        		$value['imagePath']=$profil;
        }
		return $projects;
	}

	/**
	 * Update the roles' list of an organization
	 * @param $roleTab is an array with all the roles
	 * @param type $organisationId : is the mongoId of the organisation
	 */
	public static function setRoles($roleTab, $organizationId){
		PHDB::update( Organization::COLLECTION,
						array("_id" => new MongoId($organizationId)), 
                        array('$set' => array( 'roles' => $roleTab))
                    );
	}

	 /**
	 * Update an organization field value
	 * @param String $organisationId The organization Id to update
	 * @param String $organizationFieldName The name of the field to update
	 * @param String $organizationFieldValue the value of the field
	 * @param String $userId the user Id must be admin of the organization to update it
	 * @return boolean True if the update has been done correctly. Can throw CTKException on error.
	 */
	 public static function updateOrganizationField($organizationId, $organizationFieldName, $organizationFieldValue, $userId){
	 	if (!Authorisation::isOrganizationAdmin($userId, $organizationId)) {
			throw new CTKException(Yii::t("organization", "Can not update this organization : you are not authorized to update that organization !"));	
		}
		
		$res = self::updateField($organizationId, $organizationFieldName, $organizationFieldValue);
	                  
	    return $res;
	}

	private static function updateField($organizationId, $organizationFieldName, $organizationFieldValue) {
		$dataFieldName = Organization::getCollectionFieldNameAndValidate($organizationFieldName, $organizationFieldValue);
	
		//Specific case : 
		//Tags
		if ($dataFieldName == "tags") {
			$organizationFieldValue = Tags::filterAndSaveNewTags($organizationFieldValue);
		} else if ($dataFieldName == "address") {
		//address
			if(!empty($organizationFieldValue["postalCode"]) && !empty($organizationFieldValue["codeInsee"])) {
				$insee = $organizationFieldValue["codeInsee"];
				$address = SIG::getAdressSchemaLikeByCodeInsee($insee);
				if(empty($organizationFieldValue["geo"]))
					$set = array("address" => $address, "geo" => SIG::getGeoPositionByInseeCode($insee));
			} else {
				throw new CTKException("Error updating the Organization : address is not well formated !");			
			}
		} else {
			$set = array($dataFieldName => $organizationFieldValue);	
		}

		//update the organization
		PHDB::update( Organization::COLLECTION, array("_id" => new MongoId($organizationId)), 
		                          array('$set' => $set));
		return true;
	}

	/**
	 * Add someone as admin of an organization.
	 * If there are already admins of the organization, they will receive a notification and email to 
	 * accept or not the new admin
	 * @param String $idOrganization The id of the organization
	 * @param String $idPerson The id of the person asking to become an admin
	 * @param String $userId The userId doing the action
	 * @return array of result (result => bool, msg => string)
	 */
	public static function addPersonAsAdmin($idOrganization, $idPerson, $userId) {
		$res = array("result" => true, "msg" => "You are now admin of the organization");

		$organization = self::getById($idOrganization);
		$pendingAdmin = Person::getById($idPerson);
		
		if (!$organization || !$pendingAdmin) {
			return array("result" => false, "msg" => "Unknown organization or person. Please check your parameters !");
		}
		//First case : The organization doesn't have an admin yet : the person is automatically added as admin
		$usersAdmin = Authorisation::listOrganizationAdmins($idOrganization, false);
		if (in_array($idPerson, $usersAdmin)) 
			return array("result" => false, "msg" => "Your are already admin of this organization !");

		if (count($usersAdmin) == 0) {
			Link::addMember($idOrganization, self::COLLECTION, $idPerson, Person::COLLECTION, $userId, true, "", false);
			Notification::actionOnPerson ( ActStr::VERB_JOIN, ActStr::ICON_SHARE, $pendingAdmin , array("type"=>Organization::COLLECTION,"id"=> $idOrganization,"name"=>$organization["name"]) ) ;
		} else {
			//Second case : there is already an admin (or few) 
			// 1. Admin link will be added but pending
			Link::addMember($idOrganization, self::COLLECTION, $idPerson, Person::COLLECTION, $userId, true, "", true);
			Notification::actionOnPerson ( ActStr::VERB_JOIN, ActStr::ICON_SHARE, $pendingAdmin , array("type"=>Organization::COLLECTION,"id"=> $idOrganization,"name"=>$organization["name"]) ) ;
			// 2. Notification and email are sent to the admin(s)
			$listofAdminsEmail = array();
			foreach ($usersAdmin as $adminId) {
				$currentAdmin = Person::getSimpleUserById($adminId);
				array_push($listofAdminsEmail, $currentAdmin["email"]);
			}
			Mail::someoneDemandToBecomeAdmin($organization, $pendingAdmin, $listofAdminsEmail);
			//TODO - Notification
			$res = array("result" => true, "msg" => "Your request has been sent to other admins.");
			// After : the 1rst existing Admin to take the decision will remove the "pending" to make a real admin
		}

		return $res;
	}


}
?>
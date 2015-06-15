<?php
class RemoveContributorAction extends CAction
{
    public function run($contributorId, $contributorType, $projectId)
    {
        $res = array( "result" => false , "msg" => "Something went wrong" );
		try {
			Link::disconnect($contributorId, $contributorType, $projectId, PHType::TYPE_PROJECTS,Yii::app()->session['userId'], "projects");
			Link::disconnect($projectId, PHType::TYPE_PROJECTS, $contributorId, $contributorType,Yii::app()->session['userId'], "contributors");
			$res = array( "result" => true , "msg" => "Contributeur enlevé avec succès" );			
		} catch (CTKException $e) {
			$res = array( "result" => false , "msg" => $e->getMessage() );
		}

		return Rest::json($res);
    }
}
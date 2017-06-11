<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details.
 *
 * @package    repository_nuxeoworkspaces
 * @category   repository
 * @copyright  Academie de Rennes(http://www.toutatice.fr)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/local/nuxeolibs/NuxeoAutomationClient/NuxeoAutomationAPI.php');
require_once($CFG->dirroot.'/lib/formslib.php');

class nuxeows {

    public $sessionSSO;

    private $conteners;

    private $contents;

    private $space;

    // private static QUERY = 'query' ;

    // private static GETBLOC = 'getbloc' ;

    function __construct($url, $user_name, $secret_key) {
        $interceptorSSO = new PortalSSORequestInterceptor($user_name, $secret_key);

        $client = new NuxeoPhpAutomationClient($url, $interceptorSSO);

        $this->sessionSSO = $client->getSession();
    }

    public function getsession() {
        $this->sessionSSO;
    }

    public function set_params($params) {
        $this->conteners = $params['conteners'];
        $this->contents = $params['contents'];
        $this->space = $params['space'];
    }

    public function getId($path) {

        $sql = "
            SELECT
                *
            FROM
                Document
            WHERE
                ecm:path = '".$path."' AND
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                ecm:isProxy = 0
        ";

        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        if (!empty($answer->error)) {
            return null;
        }

        $documentsArray = $answer->content;

        if (!$documentsArray) {
            return null;
        }

        $id = current($documentsArray)->getUid();

        return $id;
    }

    public function getFilesFrom($path) {

        $parentID = $this->getId($path);
        if ($parentID == null) {
            return null;
        }

        $sql = "
            SELECT
                *
            FROM
                ".implode(',', array_merge($this->conteners , $this->contents))."
            WHERE
                ecm:parentId = '".$parentID."' AND
                (ecm:primaryType IN ('".implode("','", $this->conteners)."') OR content/length > 0) AND
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                ecm:isProxy = 0
        ";

        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        return $answer;
    }

    public function getRoot() {

        $sql = "
            SELECT
                *
            FROM
                ".implode(',', $this->space)."
            WHERE
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                ecm:isProxy = 0
        ";
        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        return $answer;
    }

    public function getuserworkspacePath() {

        $sql = "
            SELECT
                *
            FROM
                UserWorkspace
            WHERE
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                ecm:isProxy = 0
        ";

        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        if (empty($answer->error)) {
            $doc = current($answer->content);
            return $doc->getPath();
        }

        return null;
    }

    public static function download($ref) {

        $interceptor = new PortalSSORequestInterceptor($ref->user, $ref->secret_key);

        $client = new NuxeoPhpAutomationClient($ref->url_nuxeo, $interceptor);

        $session = $client->getSession();
        $req = $session->newRequest('Blob.Get')->set('input', 'doc:'.$ref->filepath);
        $answer = nuxeows::send($req, 'get');
        return $answer;
    }

    public function recherche($research, $path = '/') {

        $sql = "
            SELECT
                *
            FROM
                ". implode(",", array_merge($this->conteners , $this->contents ,$this->space)). " 
            WHERE
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                (ecm:primaryType IN ('".implode("','", array_merge($this->conteners ,$this->space))."') OR
                content/length > 0) AND
                ecm:isProxy = 0 AND
                ecm:path STARTSWITH '".$path."' AND
                (ecm:fulltext = '".$research."' OR
                file:filename LIKE '%".$research."%' OR
                dc:title LIKE '%".$research."%')
        ";
        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        return $answer;
    }

    /**
     * return in array some information about the document wicht have 
     * as path $filepath
     * @param string $filepath
     * @return array file info
     */
    public function get_file_info($filepath) {
        $sql = "
            SELECT
                *
            FROM
                Document
            WHERE
                ecm:path = '".$filepath."' AND
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                ecm:isProxy = 0
        ";
        $req = $this->sessionSSO->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);

        if (!empty($answer->error) || !is_array($answer->content)) {
            return null ;
        }
        $doc = current($answer->content);

        return array(
            'id' => $doc->getUid() ,
            'title' => $doc->getTitle() ,
            'filename' => $doc->getProperty('file:filename') ,
            'type' => $doc->getType(),
            'size' => $doc->getProperty('common:size'),
            'repository' => $doc->getRepository()
        );

        // return $answer;
    }

    /**
     * check if the current session is valide
     */
    public function issessionvalide() {
        $answer = $this->sessionSSO->isValide();
        return $answer;
    }

    public static function construct_nuxeo_url($url_base) {
        $url = $url_base;

        if ($url_base [strlen($url_base) - 1] != '/') {
            $url .= '/';
        }
        $url .= 'site/automation';

        return $url;
    }

    public static function send($request, $typerequest = 'query') {

        $result = new stdClass();

        try {
            $answer = $request->sendRequest();
            if ($typerequest == 'query') {
                if ($answer) {
                    $result->content = $answer->getDocumentList();
                }
            } else {
                $result->content = $answer;
            }
        } catch (Exception $ex) {
            $result->error = $ex->getMessage();
        }

        return $result;
    }

    public function getwokspaces() {
        $workspaces = array(
            array(
                'name'=>get_string('workspaces' , 'repository_nuxeoworkspaces'),
                'path'=>'/default-domain/workspaces'
            ),
            array(
                'name'=>get_string('allworkspace' , 'repository_nuxeoworkspaces'),
                'path'=>'/'
            ),
            array(
                'name'=>get_string('userworkspace' , 'repository_nuxeoworkspaces'),
                'path'=>'/default-domain/UserWorkspaces'
            ),
       );

        return $workspaces;
    }

    public function fileExixt($ref) {

        $sql = "
            SELECT
                *
            FROM
                Document
            WHERE
                ecm:currentLifeCycleState != 'deleted' AND
                ecm:isCheckedInVersion = 0 AND
                content/length > 0 AND
                ecm:isProxy = 0 AND
                ecm:path = '".$ref->filepath."'
        ";
        $interceptor = new PortalSSORequestInterceptor($ref->user, $ref->secret_key);

        $client = new NuxeoPhpAutomationClient($ref->url_nuxeo, $interceptor);

        $session = $client->getSession();

        $req = $session->newRequest('Document.Query')->set('params', 'query', $sql)->setSchema('*');
        $answer = nuxeows::send($req);
        if (!empty($answer->error) || !is_array($answer->content)) {
            return false ;
        }

        return true;
    }
}

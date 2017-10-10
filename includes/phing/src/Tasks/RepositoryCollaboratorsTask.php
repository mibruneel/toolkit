<?php

/**
 * Github Collaborators integration.
 *
 * PHP Version 5 and 7
 *
 * @category BuildSystem
 * @package  DrupalToolkit
 * @author   DIGIT NEXTEUROPA QA <DIGIT-NEXTEUROPA-QA@ec.europa.eu>
 * @license  https://ec.europa.eu/info/european-union-public-licence_en EUPL
 * @link     https://github.com/ec-europa/toolkit/blob/master/includes/phing/src/Tasks/DocGeneratorTask.php
 */

namespace Phing\Toolkit\Tasks;

use Symfony\Component\Yaml\Dumper;
use League\CLImate\CLImate;

require_once 'phing/Task.php';

/**
 * A Phing task to manage repository collaborators.
 *
 * @category BuildSystem
 * @package  DrupalToolkit
 * @author   DIGIT NEXTEUROPA QA <DIGIT-NEXTEUROPA-QA@ec.europa.eu>
 * @license  https://ec.europa.eu/info/european-union-public-licence_en EUPL
 * @link     https://github.com/ec-europa/toolkit/blob/master/includes/phing/src/Tasks/DocGeneratorTask.php
 */
class RepositoryCollaboratorsTask extends \Task
{

    private $_githubUser        = '';
    private $_githubPass        = '';
    private $_githubCredentials = '';

    public $reference     = '';
    public $forks         = [];
    public $collaborators = [];
    public $invitations   = [];
    public $maintainers   = [];
    public $op            = '';
    public $projectId     = '';

    /**
     * Sets the project ID.
     *
     * @param string $projectId The project ID.
     *
     * @return void
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;

        $this->_githubUser = getenv('GITHUB_USER');
        $this->_githubPass = getenv('GITHUB_PASS');
        $this->_githubCredentials = $this->_githubUser. ":" .$this->_githubPass;
    }

    /**
     * Sets the repository reference name.
     *
     * @param string $reference The reference repository name.
     *
     * @return void
     */
    public function setReference($reference)
    {
        $this->reference  = $reference;
    }

    /**
     * Sets the project maintainers.
     *
     * @param string $maintainers The repository maintainers.
     *
     * @return void
     */
    public function setMaintainers($maintainers)
    {
        $this->maintainers = explode(' ', $maintainers);
    }

    /**
     * Sets the operation to be executed.
     *
     * @param string $op The operation name.
     *
     * @return void
     */
    public function setOp($op)
    {
        $this->op = $op;
    }

    /**
     * The init method: Do init steps.
     *
     * @return void
     */
    public function init()
    {
        // Init code here.
    }

    /**
     * Checks if all properties required for generating the makefile are present.
     *
     * @throws \BuildException
     *   Thrown when a required property is not present.
     *
     * @return void
     */
    protected function checkRequirements()
    {
        $required_properties = array('projectId');
        foreach ($required_properties as $required_property) {
            if (empty($this->$required_property)) {
                throw new \BuildException(
                    "Missing required property " . $required_property . "."
                );
            }
        }
    }

    /**
     * Generates a Drush make file.
     *
     * @return void
     */
    public function main()
    {
        // Check requirements.
        $this->checkRequirements();

        // Init forks, collaborators and invites information.
        $this->setForks();
        $this->setCollaborators($this->reference);
        $this->setInvitations($this->reference);

        foreach ($this->forks as $fullName => $url) {
            $this->setCollaborators($fullName);
            $this->setInvitations($fullName);
        }

        switch ($this->op) {
        case 'add':
            foreach ($this->maintainers as $user) {
                $this->addUser($user);
            }
            break;
        case 'remove':
            foreach ($this->maintainers as $user) {
                $this->removeUser($user);
            }
            break;
        default:
            $this->overview();
            break;
        }
    }

    /**
     * Set all forks for a given repository.
     *
     * @return void
     */
    protected function setForks()
    {
        $endpoint = 'repos/' . $this->reference . '/forks';
        $result = $this->repositoryQuery($endpoint);

        $forksData = json_decode($result['data']);

        if ($forksData->message != 'Not Found') {
            foreach ($forksData as $fork) {
                $this->forks[$fork->full_name] = $fork->html_url;
            }
        }
    }

    /**
     * Set all collaborators for a given repository.
     *
     * @param string $repository   Repository name.
     * @param bool   $ignore_admin Include or not admins in the list.
     *
     * @return void
     */
    protected function setCollaborators($repository, $ignore_admin = false)
    {
        $collaborators_temp = [];

        // Get contributors for reference repository.
        $endpoint = 'repos/' . $repository . '/collaborators';
        $result = $this->repositoryQuery($endpoint);

        $collaborators = json_decode($result['data']);

        foreach ($collaborators as $key => $collaborator) {
            $collaborators_temp[] = [
                'name' => $collaborator->login,
                'pull' => $collaborator->permissions->pull,
                'push' => $collaborator->permissions->push,
                'admin' => $collaborator->permissions->admin,
            ];
        }

        $this->collaborators[$repository] = $collaborators_temp;
    }

    /**
     * Set all invitations for a given repository.
     *
     * @param string $repository Repository name.
     *
     * @return void
     */
    protected function setInvitations($repository)
    {
        $invitations_temp = [];

        // Get contributors for reference repository.
        $endpoint = 'repos/' . $repository . '/invitations';
        $result = $this->repositoryQuery($endpoint);

        $invitations = json_decode($result['data']);

        foreach ($invitations as $key => $invite) {
            $invitations_temp[] = [
                'name'       => $invite->invitee->login,
                'inviter'    => $invite->inviter->login,
                'created_at' => $invite->created_at,
            ];
        }

        $this->invitations[$repository] = $invitations_temp;
    }

    /**
     * List all collaborators for a given repository.
     *
     * @return void
     */
    protected function overview()
    {
        // List reference.
        echo " https://github.com/" . $this->reference;
        echo "\n\n Current collaborators:";
        foreach ($this->collaborators[$this->reference] as $collaborator) {

            echo "\n " . $collaborator['pull'] .
                " " . $collaborator['push'] .
                " " . $collaborator['admin'] .
                "\t" . $collaborator['name'];
        }

        // List all invitation pending for Reference repository.
        if (count($this->invitations[$this->reference]) > 0 ) {
            echo "\n\n Pending invitations:";
            foreach ($this->invitations[$this->reference] as $invite) {
                echo "\n " . $invite['name'] .
                    " (invited by " . $invite['inviter'] .
                    " in " . $invite['created_at'] .")";
            }
        }
        // List forks.
        if (count($this->forks) > 0 ) {
            foreach ($this->forks as $fullname => $url) {
                echo "\n\n" . $url . " (fork):\n";
                echo " R W A\tUser";

                foreach ($this->collaborators[$fullname] as $collaborator) {

                    echo "\n " . $collaborator['pull'] .
                        " " . $collaborator['push'] .
                        " " . $collaborator['admin'] .
                        "\t" . $collaborator['name'];
                }
            }
        }
    }

    /**
     * List all collaborators for a given repository.
     *
     * @param string $user The GitHub username to be added to repository
     *
     * @return void
     */
    protected function addUser($user)
    {
        $endpoint = 'repos/' . $this->reference . '/collaborators/' . $user;

        $result = $this->repositoryQuery($endpoint);

        if ($result['status'] == '204') {
            echo "Collaborator " . $user .
                " already in the list of collaborators, no action required.\n";
        } else {
            $result = $this->repositoryQuery($endpoint, 'PUT');

            if ($result['status'] == '204') {
                echo "Collaborator " . $user .
                    " have now READ access to repository.\n";
            } else {
                if ($result['status'] == '201') {
                    echo "Collaborator " . $user .
                        " is now invited, waiting acceptance.\n";
                } else {
                    echo "Not possible add " . $user .
                        " to the list of collaborators, operation failed.\n";
                }
            }
        }
    }

    /**
     * Remove specific user from repository.
     *
     * @param string $user Name of GitHub user to be removed.
     *
     * @return void
     */
    protected function removeUser($user)
    {
        $endpoint = 'repos/' . $this->reference . '/collaborators/' . $user;
        $result = $this->repositoryQuery($endpoint);

        if ($result['status'] == '204') {
            $result = $this->repositoryQuery($endpoint, 'DELETE');
            if ($result['status'] == '204') {
                echo "Collaborator " . $user . " removed from repository.\n";
            } else {
                echo "Something happened, checking...\n";
            }
        } else {
            echo "Collaborator " . $user . " not found.\n";
        }
    }

    /**
     * Execute a query against gitHub REST API 3.
     *
     * @param string $endpoint gitHub endpoint.
     * @param bool   $type     Type of POST to execute, false by default.
     *
     * @return array Return query result.
     */
    protected function repositoryQuery($endpoint, $type = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/' . $endpoint);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_githubCredentials);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Toolkit Drupal');

        if ($type != false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["content-length: 0"]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
            if ($type == 'PUT') {
                curl_setopt(
                    $ch, CURLOPT_POSTFIELDS,
                    http_build_query(['permission' => 'pull'])
                );
            }
        }

        $result=curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        curl_close($ch);

        return [
            'status' => $status_code,
            'data' => $result
        ];
    }

}


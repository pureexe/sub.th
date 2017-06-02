<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
* for Admin delete or update user data or create new invite
* @class: AdminCtrl
**/
class AdminCtrl extends CI_Controller {
  private $user;
  public function __construct()
	{
    parent::__construct();
    $this->load->model("User")->model("Rest");
    $this->load->library('form_validation');
    $this->user = $this->User->get();
    if(empty($this->user) || $this->user['type'] != 'admin'){
      return $this->Rest->error('only admin can do this action');
    }
	}
  public function create()
  {
    $this->form_validation
      ->set_rules('username', 'username', 'required|trim|alpha_numeric|is_unique[user.username]')
      ->set_rules('email', 'email', 'required|trim|valid_email|is_unique[user.email]');
    if ($this->form_validation->run() == FALSE){
      return $this->Rest->error(validation_errors(),1);
    }
    $username = $this->input->post("username");
    $email = $this->input->post("email");
    $password = $this->input->post("password");
    //create random string if password not set
    if(empty($password)){
      $this->load->helper('string');
      $password = random_string('alnum',8);
    }
    $id = $this->User->create($username,$email,$password);
    $token = $this->User->generateInviteToken($id);
    $this->Rest->render(array(
      "id" => $id,
      "invite_token" => $token
    ));
  }
  public function remove($uid)
  {
    if(!$this->User->isExist($uid)){
      return $this->Rest->error('can\'t remove non-exist user');
    }
    $this->User->remove($uid);
    $this->Rest->render(array(
        "id" => intval($uid)
    ));
  }
  public function update($uid)
  {
    $quota = $this->input->post('quota');
    $type = $this->input->post('type');
    $user = $this->User->get($uid);
    if(empty($user)){
        return $this->Rest->error('can\'t modify non-exist user');
    }
    if(!empty($quota) && is_numeric($quota)){
      $this->load->driver('cache',array('adapter' => 'apc','backup' => 'file'));
      $this->cache->delete('quota_shorten_'.$user['username']);
      $this->User->setQuota($uid,intval($quota));
    }
    if(!empty($type) && in_array($type,array('admin','user','ban','disable'))){
      $this->User->setType($uid,$type);
    }
    $this->Rest->render(array(
      "id" => intval($uid)
    ));
  }
  public function invite($uid)
  {
    if(!$this->User->isExist($uid)){
      return $this->Rest->error('can\'t issue invite token for non-exist user');
    }
    $token = $this->User->generateInviteToken($uid);
    $this->Rest->render(array(
      "invite_token" => $token
    ));
  }
  public function list()
  {
    $limit = $this->input->get('limit');
    $page = $this->input->get('page');
    $page = empty($page)?1:$page;
    $limit = empty($limit)?10:$limit;
    $limit = $limit > 1000?10:$limit;
    $this->Rest->render(array("user"=>$this->User->list($page,$limit)));
  }
}

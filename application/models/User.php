<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
* @class: User
**/
class User extends CI_Model {
  private $userid;
  private $realUserId;
  public function __construct()
  {
    parent::__construct();
    $this->load->database();
  }
  /**
  * สำหรับสร้างรหัสบัตรเชิญที่ไม่ซ้ำกับในฐานข้อมูล
  * @return String บัตรเชิญที่ไม่ซ้ำกันในฐานข้อมูล
  * @method getUniqueInvite
  **/
  public function getUniqueInvite()
  {
    $this->load->helper('thaistring');
    while(true){
      $token = random_thai_string('car');
      $cnt = $this->db
          ->where('invite_token',$token)
          ->count_all_results('user');
      if($cnt == 0){
        break;
      }
    }
    return $token;
  }
  /**
  * สำหรับทำการสร้างบัตรเชิญอีกครั้งของบัญชีที่เคยใช้บัตรเชิญไปแล้ว (กรณีลืมรหัสผ่าน)
  * @param int รหัสผู้ใข้
  * @return String รหัสบัตรเชิญ
  * @method generateInviteToken
  **/
  public function generateInviteToken($userId)
  {
    $token = $this->getUniqueInvite();
    $this->db
      ->where('id',$userId)
      ->update('user',array(
        'invite_token' => $token
      ));
    return $token;
  }
  /**
  * สำหรับทำยกเลิกรหัสบัตรเชิญที่ไม่ใช้ (จะไม่ลบบัญชีทิ้ง)
  * @param int รหัสผู้ใข้
  * @method generateInviteToken
  **/
  public function removeInviteToken($uid)
  {
    $this->db
      ->where('id',$uid)
      ->update('user',array(
        'invite_token' => ''
      ));
  }
  /**
  * สร้างบัญชีใหม่พร้อมบัตรเชิญมาให้ใช้บัญชีดังกล่าว
  * @param String โน้ตสำหรับกำกับข้อมูล
  * @method generateInviteToken
  **/
  public function inviteTokenForNewUser($note = '')
  {
    $this->load->config('paith');
    $token = $this->getUniqueInvite();
    $data = array(
      'type' => 'user',
      'invite_token' => $token,
      'shorten_quota' => $this->config->item('shorten_quota')
    );
    if(!empty($note)){
      $data['note'] = $note;
    }
    $this->db->insert('user',$data);
    $uid = intval($this->db->insert_id());
    return array(
      'id' => $uid,
      'invite_token' => $token
    );
  }
  /**
  * อัปเดตข้อมูลผู้ใช้เมื่อมีการใช้บัตรเชิญ
  * หมายเหตุ: หากไม่ส่งชื่อผู้ใช้มาจะเป็นการเปลี่ยนรหัสผ่าน
  * แต่หากส่งมาจะเป็นการเขียนทับข้อมูลในบัญชีสำหรับผู้ใช้ใหม่
  * @param String บัตรเชิญ String รหัสผ่านที่ไม่เข้ารหัส String ชื่อผู้ใช้
  * @return boolean จริงเมื่อมีการอัปเดตข้อมูล เท็จเมื่อบัตรเชิญไม่ถูกต้อง
  * @method setPasswordInvite
  **/
  public function setPasswordInvite($invite_token,$password,$username = '')
  {
    $this->load->library('phpass');
    $hash = $this->phpass->hash($password);
    $data = array(
      'invite_token' => '',
      'password' => $hash
    );
    if(!empty($username)){
      $data['username'] = $username;
    }
    $this->db
      ->where('invite_token',$invite_token)
      ->update('user',$data);
    return empty($this->db->affected_rows());
  }
  /**
  * สำหรับค้นหาชื่อผู้ใช้และรหัสผู้ใช้ด้วยบัตiเชิญ
  * @param String บัตรเชิญ
  * @return null|assoc_array ประกอบด้วย id และ username
  * @method getByInviteToken
  **/
  public function getByInviteToken($invite_token){
    $query = $this->db
      ->select('id,username')
      ->from('user')
      ->where('invite_token',$invite_token);
    $data = $query->get()->result_array();
    return empty($data)?null:$data[0];
  }
  /**
  * สำหรับดึงข้อมูลผู้ใช้จริง กรณีมีการสลับบัญชี (Override)
  * @return assoc_array ข้อมูลของผู้ใช้ ตามตาราง user
  * @method getReal
  **/
  public function getReal()
  {
    $uid = $this->getRealId();
    return $this->get($uid);
  }
  /**
  * ดึงรหัสประจำตัวของผู้ใช้จริง ผ่าน session
  * หมายเหตุ: ควรปิด session ทันที เพื่อป้องกันการ blocking ตามที่ CI เตือนไว้
  * @return int รหัสประจำตัวผู้ใช้จริง
  * @method getReal
  **/
  public function getRealId(){
    if(empty($this->realUserId)){
      $this->load->library('session');
      session_write_close();
      $this->realUserId = $this->session->userid;
    }
    return $this->realUserId;
  }
  /**
  * ดึงรหัสประจำตัวของผู้ใช้ หากมีการ override อยู่จาก session
  * ให้ตอบกลับเป็นเลขจาก session แทน
  * หมายเหตุ: ควรปิด session ทันที เพื่อป้องกันการ blocking ตามที่ CI เตือนไว้
  * @return int รหัสประจำตัวผู้ใช้
  * @method getId
  **/
  public function getId()
  {
    if(empty($this->userid)){
      $this->load->library('session');
      session_write_close();
      $this->userid = $this->session->userid_override;
      if(empty($this->userid)){
        $this->userid = $this->session->userid;
      }else{
        $this->realUserId = $this->session->userid;
      }
    }
    return $this->userid;
  }
  /**
  * ดึงข้อมูลผู้ใช้ หาก override อยู่ให้ตอบกลับข้อมูลที่ override
  * หรือหากมีการส่งพารามิเตอร์เข้ามาให้ตอบกลับข้อมูลของพารามิเตอร์
  * หมายเหตุ: หากมีการ override ให้ตอบกลับว่า override_by ด้วย
  * @param int รหัสประจำตัวผู้ใช้
  * @return assoc_array ข้อมูลผู้ใช้ตาม ตาราง user
  * @method getId
  **/
  public function get($uid = 0)
  {
    if(empty($uid)){
      $uid = $this->getId();
    }
    $query = $query = $this->db
      ->select('id,username,type,shorten_quota,note,ban_note')
      ->from('user')
      ->where('id',$uid);
    $users = $query->get()->result_array();
    if(empty($users)){
      return null;
    }else{
      if(!empty($this->realUserId)){
        $users[0]['override_by'] = $this->realUserId;
      }else{
        unset($users[0]['note']);
      }
      if(empty($users[0]['ban_note'])){
        unset($users[0]['ban_note']);
      }
      $users[0]['id'] = intval($users[0]['id']);
      $users[0]['shorten_quota'] = intval($users[0]['shorten_quota']);
      return $users[0];
    }
  }
  /**
  * ตรวจสอบว่ารหัสผู้ใชดังกล่าวมีอยู่จริงหรือไม่
  * @param int รหัสผู้ใช้
  * @return boolean
  * @method isExist
  **/
  public function isExist($uid)
  {
    $cnt = $this->db
        ->where('id',$uid)
        ->count_all_results('user');
    return $cnt > 0;
  }
  /**
  * ลบผู้ใช้ออกจากระบบ
  * หมายเหตุ: ลบแค่ตาราง user
  * @param int รหัสผู้ใช้
  * @method remove
  **/
  public function remove($uid)
  {
    $this->db
      ->where('id', $uid)
      ->delete('user');
  }
  /**
  * กำหนดประเภทของผู้ใช้
  * มีด้วยกัน 4 ประเภทคือ admin,user,ban,disable
  * หากตั้ง user เป็น ban หรือเอาออกจาก ban ต้องอัปเดตตาราง path ให้ใช้งานได้ด้วย
  * หากเอาสถานะออกจาก ban หรือ disable ต้องเคลีย user.ban_note ด้วย
  * @param int รหัสผู้ใช้, String ประเภท
  * @method setType
  **/
  public function setType($uid,$type)
  {
    $old_type = $this->db
      ->select('type')
      ->from('user')
      ->where('id',$uid)
      ->get()
      ->result_array()[0]['type'];
    if($old_type == 'ban' && $type != 'ban'){
      $this->db
        ->where('owner',$uid)
        ->update('path',array(
          'status' => ''
        ));
    }else if($old_type != 'ban' && $type == 'ban'){
      $this->db
        ->where('owner',$uid)
        ->update('path',array(
          'status' => 'ban'
        ));
    }
    $data = array(
      'type' => $type
    );
    if($type != 'ban' && $type != 'disable'){
      $data['ban_note'] = '';
    }
    $this->db
      ->where('id',$uid)
      ->update('user',$data);
  }
  /**
  * สำหรับบันทึก note ของผู้ใช้ให้ผู้ดูแลจำได้ว่าเป็นใคร
  * @param int รหัสผู้ใช้, String โน้ต
  * @method setNote
  **/
  public function setNote($uid,$note)
  {
    $this->db
      ->where('id',$uid)
      ->update('user',array(
        'note' => $note
      ));
  }
  /**
  * สำหรับหน้าแรกหากโดนแบนหรือระงับ จะแสดงข้อความนี้ให้ผู้ใช้ดูโดยให้ผู้ดูแลเป็นคนตั้ง
  * @param int รหัสผู้ใช้, String โน้ตเมื่อโดนแบน
  * @method setNote
  **/
  public function setBanNote($uid,$note)
  {
    $this->db
      ->where('id',$uid)
      ->update('user',array(
        'ban_note' => $note
      ));
  }
  /**
  * สำหรับตั้งโควต้าต่อวันที่ผู้ใช้จะสร้างลิงก์ได้
  * @param int รหัสผู้ใช้,int ปริมาณที่สร้างลิงค์ได้ต่อวัน
  * @method setQuota
  **/
  public function setQuota($uid,$cnt)
  {
    $this->db
      ->where('id',$uid)
      ->update('user',array(
        'shorten_quota' => $cnt
      ));
  }
  public function all($page,$limit)
  {
    $page = ($page-1)*$limit;
    $query = $this->db
      ->select('id,username,type,note,invite_token,shorten_quota,ban_note')
      ->from('user')
      ->order_by('id','DESC')
      ->limit($limit, $page);
    $output = $query->get()->result_array();
    foreach ($output as &$o) {
      $o['id'] = intval($o['id']);
      $o['shorten_quota'] = intval($o['shorten_quota']);
    }
    return $output;
  }
}

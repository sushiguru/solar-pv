<?php
	
	# Class was written as a controller in codeigniter but pretty straightforward to translate into
	# your own framework.  The important point to note is that the response must be a JSON string
	# with two variables, as defined

class Pv extends Controller{

	function Pv()
	{
		parent::Controller();
		$this->load->model('pv_model');
	}

	function put_data(){
		# Function expects to receive a POST request which is an array of key->value pairs for insert.
		# Anything else will just fail silently
		
		$this->db->insert('your.table_name',$_POST);
		$json = array();
		$liid = $this->db->insert_id();
		if(!$liid || $liid==0 || $liid=='')
		{
			$liid = 0;
		}
		$json['liid'] = $liid;
		$json['errno'] = $this->db->_error_message();
		$json = json_encode($json);
		print $json;
	}
	
	function index(){
		error_reporting(0);
		$data['main_content'] = 'pv';
		$data['page_title'] = 'Power';
		$lat=56.312724;
		$lng=-3.008714;
		$d = $this->input->post('d');
		$data['dates'] = $this->pv_model->getDates($d);
		$data['data'] = $this->pv_model->getData($d,$lat,$lng);
		$this->load->view('template', $data);
	}
			
}

?>
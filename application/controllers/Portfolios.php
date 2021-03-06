<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Portfolios extends MY_Controller{

	function __construct(){
	    parent::__construct();
//		$this->load->library(array('form_validation'));
//		$this->load->helper(array('language'));
//		$this->load->model('stock');
//		$this->load->model('portfolio');
//		$this->load->library('table');
//
//		$this->form_validation->set_error_delimiters($this->config->item//('error_start_delimiter', 'ion_auth'), $this->config->item//('error_end_delimiter', 'ion_auth'));
        }
        
       
	
/*
************************************************************************

*/
        
	public function index(){
		 if (!$this->ion_auth->logged_in()){	redirect('auth/login', 'refresh');	}
			$this->load->helper('form');
			$this->load->model('ion_auth_model');
		 
		 $data['portfolio_stocks'] = $this->portfolio->find_all();
		 
		 $this->load->view('dressings/header');
		 
		 $this->load->view('dressings/navbar');
		 $this->load->view('list_portfolios', $data);
		 
		 $this->load->view('dressings/footer');
		
		
		
	}
	
/*
************************************************************************

*/
	
	public function add(){
		if(isset($_POST)){
			$this->form_validation->set_rules('portfolio_name', 'Porfolio Name', 'required|is_unique[portfolios.portfolio_name]');
	        $this->form_validation->set_rules('beginning_cap', 'Starting Capital', 'required|numeric|greater_than[0]');       
			if($this->form_validation->run()){
				$portfolio = new Portfolio;
				$portfolio->portfolio_name = $this->input->post('portfolio_name');
				$portfolio->portfolio_description = $this->input->post('portfolio_description');
				$portfolio->beginning_cap = $this->input->post('beginning_cap');
				$portfolio->current_cap = $portfolio->beginning_cap;
				$portfolio->starting_date = date("Y-m-d H:i:s");
				$portfolio->user_id = $this->input->post('user_id') + 1;
				$checked = $this->input->post('commision_bool');
				if((int) $checked == 1){
					$portfolio->commision_bool=1;
				}else{
					$portfolio->commision_bool=0;
				}
				
				$portfolio->commision = $this->input->post('commision');
				$portfolio->save();
				redirect('portfolios');
			}else{
				$this->data['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
				$this->data['charts']=0;
				$this->load->view('dressings/header');
				$this->load->view('dressings/navbar');
				$this->load->view('add_portfolio', $this->data);
				$this->load->view('dressings/footer');	
			
				
			}
		}
	
		
		
		
	}
	
/*
************************************************************************

*/
	public function delete(){
		$id = $this->uri->segment(3);
		$post = Portfolio::find_by_id($id);
		$post->delete();
		redirect('portfolios');
	}
	
	
/*
************************************************************************

*/
	public function view(){
		$portfolio_id = $this->uri->segment(3);
		
		$portfolio = Portfolio::find_by_id($portfolio_id);
		$data['portfolio'] = $portfolio;
		
		
		if(null!==$this->input->post('submit_stock_query')){
	    	//fill this section if a certain post value is submitted, like a buy
	    	$stocks_query=array();
	    	foreach($_POST as $symbol){
	    		$stocks_query[] = $symbol;
	    	}
	    	array_pop($stocks_query);
	    	$stocks_query = $this->stock->live_quotes($stocks_query);
	    	// Generate table of queried stocks
	    	for($i=0;$i<count($stocks_query); $i++){
	    		//Add Buy form
	    		$stocks_query[$i]['']= 
	    		form_open('portfolios/buy')
	    		.form_hidden('stock_symbol', $stocks_query[$i]['symbol'])
	    		.form_hidden('portfolio_id', $portfolio_id)
	    		.form_input( array('name'=>'shares','placeholder'=>'Shares', 'type'=>'number'))
	    		.form_submit('buy_stock', 'Buy')
	    		.form_close();
	    	}
	    	$data['stocks_query']=$stocks_query;
	    }
	    
	    $data['outstanding_stocks'] = $this->get_current_stock_value();
	    
	    //Generate table of current stocks
	    
	    $this->db->where('sale_time', NULL);
	    $data['portfolio_stocks'] = $this->stock->find_by('portfolio_id', $portfolio_id);
	    $data['outstanding_stock_value'] = $this->get_current_stock_value($data['portfolio_stocks']);
	    $data['gains'] = $this->portfolio_gains($data['portfolio_stocks'], $portfolio);
		//preprint($data['portfolio_stocks']);
		
		
		
		//Get historical data of stock purchases
		$this->db->where('sale_time !=', NULL);
		$trades = $this->stock->find_by('portfolio_id', $portfolio_id);
		
		//Generate Table
		 $this->table->set_template(array('table_open'=>"<table class='table table-striped table-bordered table-hover' id='history_table'>"));
		$this->table->set_heading('Security', 'Shares', 'Gains', 'Portfolio Value','Time Bought', 'Opening Price', 'Time Sold', 'Closing Price');
		$chart_vars = array();
		foreach($trades as $trade){
			$symbol=$trade->symbol; $shares = $trade->shares; $purchase_price = $trade->purchase_price; $sale_price = $trade->sale_price;
			$gains = $shares*(($sale_price-$purchase_price)/$purchase_price);
			
			$trade_capital = $portfolio->beginning_cap + $gains;
			
			$chart_vars[]=array('time'=>strtotime($trade->purchase_time), 'value' => $trade_capital);
			
			if($gains > 0 ){ $gains_cell = array('data' =>print_money($gains), 'class'=>'success');}elseif($gains<0){  $gains_cell = array('data' =>print_money($gains), 'class'=>'danger');}
			$this->table->add_row($symbol, $shares, $gains_cell,print_money($trade_capital), $trade->purchase_time, print_money($purchase_price), $trade->sale_time, print_money($sale_price));
		}
		
		$data['chart_vars']=json_encode($chart_vars);
		$data['historical_trades'] = $this->table->generate();
		
		//DISPLAY PAGE
		$data['charts']=TRUE;
		$this->load->view('dressings/header');
		$this->load->view('dressings/navbar');
	    $this->load->view('stocks', $data);
	    $this->load->view('dressings/footer');
	}
	
/*
************************************************************************

*/
	public function sell(){
		
		//Add logic to make sure stock isn't already sold 
		//i.e. 
		$this->load->model(array('portfolio', 'stock'));
		$id = $this->input->post('trade_id');
		$stock = Stock::find_by_id($id);
		$stock->sale_time = date("Y-m-d H:i:s");
		$stock->sale_price = $this->input->post('current_val');
		$stock->save();
		$portfolio = Portfolio::find_by_id($this->input->post('portfolio_id'));
		if($portfolio->commision_bool == 1){
			$portfolio->current_cap = $portfolio->current_cap + ($stock->sale_price * $stock->shares)-$portfolio->commision;
		}else{
			$portfolio->current_cap = $portfolio->current_cap + ($stock->sale_price * $stock->shares);
		}
		
		$portfolio->last_trade = $stock->id;
		$portfolio->save();
		redirect('portfolios/view/'.$stock->portfolio_id);
		
	}
	/*
************************************************************************

*/
	public function buy(){
		$this->load->model(array('portfolio', 'stock'));
		$stock_symbol = $this->input->post('stock_symbol');
		$stock_info = $this->stock->single_quote($stock_symbol);
		$shares = $this->input->post('shares');
		$stock = new Stock;
		$stock->portfolio_id = $this->input->post('portfolio_id');
		$stock->user_id = Portfolio::find_by_id($this->input->post('portfolio_id'))->user_id;
		$stock->symbol = $this->input->post('stock_symbol');
		//need to add market open date verification
		$stock->purchase_time = date("Y-m-d H:i:s");
		$stock->purchase_price = $stock_info['price'];
		$stock->shares = $this->input->post('shares');
		$stock->save();
		$portfolio = Portfolio::find_by_id($this->input->post('portfolio_id'));
		//$portfolio->current_cap = $portfolio->current_cap - ($stock->purchase_price * $stock->shares);
		if($portfolio->commision_bool == 1){
			$portfolio->current_cap = $portfolio->current_cap - ($stock->purchase_price * $stock->shares);
			$portfolio->current_cap = $portfolio->current_cap - $portfolio->commision;
		}else{
			$portfolio->current_cap = $portfolio->current_cap - ($stock->purchase_price * $stock->shares);
		}
		
		$portfolio->last_trade = $stock->id;
		$portfolio->save();
		redirect('portfolios/view/'.$stock->portfolio_id);
		
	}
/*
************************************************************************

*/
	
	public function get_current_stock_value($stocks=""){
		$value = 0;
		if(!empty($stocks)){
			foreach($stocks as $stock){
				$value += ($stock->shares * $this->stock->get_price($stock->symbol));
			}
		}
		return $value;
	}
	public function portfolio_gains($stocks="", $portfolio=""){
		$stock_val = $this->get_current_stock_value($stocks);
		$gains = ($portfolio->current_cap + $stock_val)/$portfolio->beginning_cap;
		return percentage($gains-1);
	}
}

<?php

class Tables extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
	}
	public function headnav()
    {
        $this->lang->load('commons');		
		$data['common_lang_set'] = $this->lang->line('common_lang_set');
		$data['common_title'] = $this->lang->line('common_title');
		$this->load->view('header',$data);
        $data['common_table_view'] = $this->lang->line('common_table_view');		
		$data['common_table_list'] = $this->lang->line('common_table_list');
		$data['common_create_table'] = $this->lang->line('common_create_table');
        
        $data['common_table_deltable'] = $this->lang->line('common_table_deltable');
        $data['common_monitor'] = $this->lang->line('common_monitor');
        $this->load->view('nav_bar',$data);  
    }
	public function Index()
	{
	    $this->headnav();
		$this->load->model('hbase_table_model','table');
        $hbaseinfo =$this->table->get_hbase_info();
        $hbasedata=json_decode($hbaseinfo,true);
        foreach($hbasedata["beans"] as $mbean)
         {
            if ($mbean["name"] == "hadoop:service=HBase,name=Info") 
            {
                $data['version']=$mbean['version'].', r'.$mbean['revision'];  
            }
            elseif ($mbean["name"] =="hadoop:service=Master,name=Master")
            {
                $data['ServerName']=explode(",",$mbean["ServerName"]);
                $data['ZookeeperQuorum']=$mbean["ZookeeperQuorum"];
                $data['DeadRegionServers']=$mbean["DeadRegionServers"];
                $data['AverageLoad']=$mbean['AverageLoad'];
                $data['MasterStartTime']=round($mbean["MasterStartTime"]/1000, 0);
                $data["live_regionservers"] = count($mbean["RegionServers"]);
                $data["Coprocessors"]=$mbean["Coprocessors"]; 
            }            
         }
		
		$this->load->view('div_fluid');
		$this->load->view('div_row_fluid');
		$this->load->view('table_lists',$data);		
		$this->load->view('table_admin', $data);
		
		$this->load->view('div_end');
		$this->load->view('div_end');
		
		$this->load->view('footer');
	}
	
	public function TableList()
	{
		$this->load->model('hbase_table_model', 'table');
		$table_list = $this->table->get_table_names();
		$table_list = array('table_names' => $table_list);
		echo json_encode($table_list);
	}
	
    public function AddTable()
	{
	    $tablename=$this->input->post("tablename");        
        if(strlen($this->GetTableRegions($tablename))==2)
        {
            $column=$this->input->post("column");
            $maxversions=$this->input->post("maxversions");
            $compression=$this->input->post("compression");
            $columnarr=explode(",",$column);
            $maxversionsarr=explode(",",$maxversions);
            $compressionarr=explode(",",$compression);
             $columns=array();
            $this->load->model('hbase_table_model', 'table');        
            foreach($columnarr as $index=>$val)
             {
                $coldes=new ColumnDescriptor();
                $coldes->name=$val.":";
                $coldes->maxVersions=(int)$maxversionsarr[$index];
                $coldes->compression=$compressionarr[$index];
                array_push($columns,$coldes);                        
             }
    	    
    		$result = $this->table->create_table($tablename,$columns);
        }
        else
        {
            $result="table already exsits";
        }		
		echo($result);
	}
    
	public function GetTableRegions($table_name)
	{
		$this->load->model('hbase_table_model', 'table');
		$regions = $this->table->get_table_regions($table_name);
		return json_encode($regions);
	}
    
    public function GetDescriptors($table_name)
	{
		$this->load->model('hbase_table_model', 'table');
		$descriptors = $this->table->get_table_descriptors($table_name);
		echo json_encode($descriptors);
	}
    public function GetColumn($table_name)
    {
        $this->load->model('hbase_table_model', 'table');
		$descriptors = $this->table->get_table_descriptors($table_name);
        $column="";
		foreach($descriptors as $key=>$value)
         {
            $column.=str_replace(":","",$key).',';
         }         
        $column=rtrim($column,",");
        return $column; 
    } 
    
    public function filtervalue($value)
    {
       $value=str_replace("\"","\\\"",$value); 
       $value=json_encode($value);
       if(preg_match("/u0/",$value))
         {
           $value="";
         }
       $value=json_decode($value); 
       return $value; 
    }
    public function GetTableRecords($table_name)
    {
        $this->load->model('hbase_table_model', 'table');
        $records= $this->table->get_table_records($table_name);        
        $result="";
        foreach($records as $index=>$cols){            
             foreach($cols->columns as $key=>$vals)
               {  
                  $row=$cols->row;
                  $row=$this->filtervalue($row);
                  
                  $column=explode(":",$key);
                  $column[0]=$this->filtervalue($column[0]);
                  $column[1]=$this->filtervalue($column[1]);
                  $value=$vals->value;
                  $value=$this->filtervalue($value);
                  //$value=json_encode($value);
                  //$value=str_replace("{","\{",$value);                
                  $result=$result."{\"row\":\"".$row."\",\"columnfamily\":\"".$column[0]."\",\"columnqualifier\":\"".$column[1];
                  $result=$result."\",\"timestamp\":\"".$vals->timestamp."\",\"value\":\"".$value."\"},";
               }
        }
        $result=rtrim($result,",");
        $result= "[".$result."]";
        print_r($result);       
    }
    
    public function UpdateRecords($table_name)
    {
       $mutation=$this->input->get("models");      
       $this->load->model('hbase_table_model', 'table');      
       $mutationarr=json_decode($mutation,true);
       $row=$mutationarr[0]["row"];
       $timestamp=intval($mutationarr[0]["timestamp"]);       
       $column=$mutationarr[0]["columnfamily"].":".$mutationarr[0]["columnqualifier"];       
       $value=$mutationarr[0]["value"];  
       $mutations = array(  
        new Mutation( array(
        'isDelete'=>0,  
        'column' => $column,  
        'value' => $value          
        ) ),  
        );
       $colres=$this->GetColumn($table_name);  
       if(strpos($colres,$column=$mutationarr[0]["columnfamily"]))
       {                   
          $result=$this->table->mutate_rowts($table_name,$row,$mutations,$timestamp);
          $mutationarr[0]["result"]=$result;
       }
       else
       {
          $mutationarr[0]["result"]="column family not exist";
       }        
            
       echo(json_encode($mutationarr));        
    }
    
     public function DestroyRecords($table_name)
    {
       $mutation=$this->input->get("models");
       $callback=$this->input->get("callback");
       $this->load->model('hbase_table_model', 'table');      
       $mutationarr=json_decode($mutation,true);
       $row=$mutationarr[0]["row"];
       $timestamp=intval($mutationarr[0]["timestamp"]);        
       $column=$mutationarr[0]["columnfamily"].":".$mutationarr[0]["columnqualifier"];
       $value=$mutationarr[0]["value"];  
       $mutations = array(  
        new Mutation( array(
        'isDelete'=>1,  
        'column' => $column,  
        'value' => $value          
        ) ),  
        );   
       $result=$this->table->mutate_rowts($table_name,$row,$mutations,$timestamp);
       $mutationarr[0]["result"]=$result;
       echo(json_encode($mutationarr));
        
    }
    
    
    public function ListTableRecords($table_name)
    {
        $this->headnav();
		$this->load->model('hbase_table_model','table');
		$this->load->view('div_fluid');
		$this->load->view('div_row_fluid');		
		$data['tablename']=$table_name;	
		$this->load->view('table_lists',$data);        	
		$this->load->view('table_records',$data);		
		$this->load->view('div_end');
		$this->load->view('div_end');		
		$this->load->view('footer');
    }
    public function TruncateTable($table_name)
    {
        $this->load->model('hbase_table_model', 'table');
        $result=$this->table->truncate_table($table_name);
        echo($result);
    }
    
    public function DelTable($table_name)
    {
        $this->load->model('hbase_table_model', 'table');
        $this->table->disable_table($table_name);
        $result=$this->table->delete_table($table_name);
        echo($result);
    }
    
    public function DelAllTable()
    {
       $tables=$this->input->post("tables");
       $tablesarr=explode(";",$tables);
       foreach($tablesarr as $tablename)
        {
           $this->DelTable($tablename); 
        }
       echo "tables deleted success";
        
    }
}

?>
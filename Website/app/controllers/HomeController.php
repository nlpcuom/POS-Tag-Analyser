<?php

class HomeController extends Controller{
    public function __construct($controller, $action) {
        parent::__construct($controller, $action);
        
    }

    public function indexAction() {                   //queryParam will be passed into the method 

        $this->view->render('home/index');
    }

    public function allwordsAction(){
        $results_per_page = 50;
        $datatable='AllWords';

        if (isset($_GET["page"])) {
            $page  = $_GET["page"];
        }
        else{
            $page=1;
        };
        $start_from = ($page-1) * $results_per_page;
        $sql = "SELECT * FROM ".$datatable." ORDER BY ID ASC LIMIT $start_from, ".$results_per_page;
        $_db=DB::getInstance();

        $results=[];
        $resultsQuery = $_db->query($sql,[]);
        $resultsQuery=$resultsQuery->results();

        if($resultsQuery){
            foreach($resultsQuery as $result) {

                $obj = new Model('AllWords');
                $obj->populateObjData($result);
                $results[] =$obj;
            }
        }

        $this->view->searchResults=$results;

        $sql = "SELECT COUNT(ID) AS total FROM ".$datatable;
        $resultsQuery = $_db->query($sql,[]);
        $row = $resultsQuery->results();
        $this->view->total_pages = ceil($row[0]->total / $results_per_page);

        $this->view->render('home/allwords');
    }

    public function tagAction() {                   //queryParam will be passed into the method

        $tag=new Tag();
        $this->view->searchResults=$tag->_tagList;
        $this->view->render('home/tag');
    }

    public function wordAction(){
        $results_per_page = 50;

        if(isset($_POST["search-submit"])){
            $word=new Word();
            if (empty($_POST["search_key"])){
                $this->view->searchResults=[];
            }else{
                $this->view->searchResults=$word->searchWord($_POST["search_key"]);
            }


            $this->view->total_pages = 0;

            $this->view->render('home/word');
        }
        else{
            if (isset($_GET["page"])) {
                $page  = $_GET["page"];
            }
            else{
                $page=1;
            };
            $start_from = ($page-1) * $results_per_page;
            $word=new Word();

            $this->view->searchResults=$word->getWords($start_from,$results_per_page);

            $this->view->total_pages = ceil($word->getRecordCount() / $results_per_page);

            $this->view->render('home/word');
        }


    }

    public function loadtagsAction(){
        if(isset($_POST["word"])){
            $tag=new Tag();
            $res=$tag->getUniqueTags($_POST["word"]);
            echo Table::getTagTable($res);
        }
    }

    public function loadtagIDsAction(){
        if(isset($_POST["word"])and isset($_POST["tag"])){

            $results_per_page = 50;
            if(isset($_POST["pgNumber"])){
                $page=(int)$_POST["pgNumber"];
            }
            else{
                $page=1;
            }

            $start_from = ($page-1) * $results_per_page;

            $tag=new Tag();
            $res=$tag->getTagIDs($_POST["word"],$_POST["tag"],$start_from,$results_per_page);
            $tagIDCount=$tag->getTagIDsCount($_POST["word"],$_POST["tag"]);

            $IDstart=($page-1)*$results_per_page;


            echo Table::getTagIDTable($res,$IDstart).Helper::getPageList($_POST["word"],$_POST["tag"],$tagIDCount,$results_per_page);
        }
    }



    public function  tagtowordAction(){
        $tag=new Tag();
        if(isset($_POST["search-submit"])){

            if (empty($_POST["search_key"])){
                $this->view->tagList=[];
            }else{
                $this->view->tagList=$tag->searchTag($_POST["search_key"]);
            }

            $this->view->render('home/tagtoword');
        }
        else{

            $this->view->tagList=$tag->getTagList();
            $this->view->render('home/tagtoword');
        }
    }

    public function  loadtagWordsFullAction(){
        if(isset($_POST["tag"])){

            $results_per_page = 50;
            if(isset($_POST["pgNumber"])){
                $page=(int)$_POST["pgNumber"];
            }
            else{
                $page=1;
            }

            $start_from = ($page-1) * $results_per_page;

            $startWord=($page-1)*$results_per_page;

            $tag=new Tag();
            $res=$tag->getTagtoWordListFull($_POST["tag"],$start_from,$results_per_page);
            $wordCount=$tag->getTagtoWordCount($_POST["tag"]);
            echo Table::getWordTableFull($_POST["tag"],$res,$startWord).Helper::getWordPageList($_POST["tag"],$wordCount,$results_per_page);
        }
    }

    public function setupAction(){
        if(isset($_POST["load-submit"])){
            $error=false;
            $this->view->_success=false;

            if (file_exists("Corpus.txt")) unlink("Corpus.txt");

            $pathCmp=explode(DS,ROOT);
            $newPath=join(DS,array_slice($pathCmp,0,count($pathCmp)-1));
            $newPath.=DS."Preprocessing".DS."merge.py";

            if(empty($_FILES["filePath"]["name"][0])){
                $error=true;
                $this->view->msg[]="No file is selected.";
            }
            else{
                for($i=0;$i<count($_FILES["filePath"]["name"]);$i++) {

                    $filePath=$_FILES['filePath']["tmp_name"][$i];

                    $command = escapeshellcmd($newPath." ".$filePath." \"".$_FILES['filePath']["name"][$i]."\"" );
                    $output = shell_exec($command);
                    
                    if(trim($output)=="Done"){
                        $this->view->msg[]="";
                    }
                    else{
                        $error=true;
                    }

                }
            }

            $newPath=join(DS,array_slice($pathCmp,0,count($pathCmp)-1));
            $newPath.=DS."Preprocessing".DS."preprocessing.py";


            $command = escapeshellcmd($newPath." ".ROOT.DS."Corpus.txt" );

            if(!$error){

                $output = shell_exec($command);
                
                if(trim($output)=="ok"){
                    $this->view->msg[]="";
                }
                else{
                    $error=true;
                }
            }

            $newPath=join(DS,array_slice($pathCmp,0,count($pathCmp)-1));
            $newPath.=DS."Preprocessing".DS."sorter.py";

            $command = escapeshellcmd($newPath." ".ROOT.DS."Corpus.txt" );

            if(!$error){

                $output = shell_exec($command);
          
                if(trim($output)=="ok"){
                    $this->view->msg[]="";
                }
                else{
                    $error=true;
                }
            }
         
            if(!$error){
                if(DB::reloadDB()){
                    $this->view->msg=["Successfully Loaded!"];
                }
                else{
                    $error=true;
                }
            }

            if($error){
                $this->view->msg[]="Load Failed!";
            }
            $this->view->_success=!$error;
        }
        $this->view->render('home/setup');
    }

    public function helpAction() {
        $this->view->render('home/help');
    }

}


<?php



class thfView  extends thfSingleton
{
	//use thfHeader;
	//use thfDirTools;
	//use Autoframe\Core\Router\Module\thfModuleTools;
	protected $selfWrapTags1=array('br','hr','img','meta','link','input');
	protected $selfWrapTags2=array('!doctype','!--');
	protected $spaceChars=array(' ',"\r","\n","\t",'>');
	
	protected $view=array(
		'output'=>'data', //  html, json, service(json, soap,middleware), data(pdf,picture,text,)
		'json'=>null,
		'service'=>null,
		);
	public $dom=array();
	private $domBackup=array();
	public $domParse=array();
	private $e;
	private $alias=array('dom'=>'',);
	
	function getAlias(){return $this->alias;}
	function fixDomNodesAliases(){
		$this->alias=array('dom'=>'',);
		$this->refferenceDomNodeWalk($this->dom,'');
	}
	function refferenceDomNodeWalk($dom,$parent){
		foreach($dom as $ni=>$tag){
			if(!is_numeric($ni)){continue;}

			$tag['n']=trim($parent.','.$ni,',');
			if(isset($tag['as'])){
				$this->alias[ $tag['as'] ] = $tag['n'];
			}
			$level_str='';
			foreach(explode(',',$tag['n']) as $depth){
				if(strlen($depth) && is_numeric($depth)){
					$level_str.='['.$depth.']';
				}
			}
			$domLevel = 'dom'.$level_str;
			$this->$domLevel=$tag;
			//eval('$this->dom'.$level_str.'=$tag;'); //echo '$this->dom'.$level_str.'=$tag;'."\r\n";
			$this->refferenceDomNodeWalk($tag,$tag['n']);
		}
	}
	
	function createTag($tag){
		$selfWrap=0;
		if(in_array(strtolower($tag),$this->selfWrapTags1)){$selfWrap=1;}
		if(in_array(strtolower($tag),$this->selfWrapTags2)){$selfWrap=2;}
		if(!$tag){$selfWrap=2;}//text node
		
		$this->e=array(
			't'=>($selfWrap==2?strtoupper($tag):$tag),//tag
			'n'=>null, //node depth and index : '2,2,4,7'
			's'=>$selfWrap, // 0= <div></div>  1 = <br /> or <img />    2= <!DOCTYPE HTML>
			//'as'=>'head', //alias
			//'t'=>null, //text
			//'html'=>null, //if html value is present, then the child nodes are ignored
			'a'=>null, //array(),//attributtes
		);
		return $this;
	}

	function findDomNode($target_node_or_alias){
		$node=isset($this->alias[$target_node_or_alias])? $this->alias[$target_node_or_alias] : $target_node_or_alias;	
		$nodes=explode(',',$node);

		$level_str='';
		$nnode='';
		if(strlen($node)<1 || !$nodes){}
		else{
			foreach($nodes as $depth){
				if(strlen($depth) && is_numeric($depth)){
					$level_str.='['.$depth.']';
					$nnode.= (strlen($nnode)?',':'').$depth;
				}
			}
		}
		$domLevel = 'dom'.$level_str;
		return array(isset($this->$domLevel),$nnode);
	}
	function loadNode($target_node_or_alias){
		$this->e=null;
		list($dom_path,$node)=$this->findDomNode($target_node_or_alias);
		if($dom_path){
			eval('$this->e='.$dom_path.';');
		}
		return $this;
	}
	function appendChild($target_node_or_alias,$as_new_alias='',$noConflict=1){
		if(count($this->e)<4){ return false;}
		list($dom_path,$node)=$this->findDomNode($target_node_or_alias);
		if(!$dom_path[0]){
			if($noConflict){
				$dom_path=$this->dom;
				$node=''; //force to top level
			}
			else{return null;}
		}
		//eval('$parentNode= '.$dom_path.';');
		$newNodeI=$this->maxNodeI($parentNode = $dom_path) + 1;
		
		$newNode=trim($node.','.$newNodeI,',');
		$this->e['n']=$newNode;
		if($as_new_alias){ $this->e['as']=$as_new_alias; }
//		echo $node.'~'.$target_node_or_alias; prea($this->e);
		eval($dom_path.'['.$newNodeI.'] = $this->e;');
//		prea($dom_path.'['.$newNodeI.'] = $this->e;');		echo '<hr>';
		if($as_new_alias){
			$this->alias[$as_new_alias]=$newNode;
		}
		return $this;
	}
	
	function maxNodeI($e){
		if(!is_array($e)){return false;}
		$max=-1;
		foreach($e as $k=>$v){
			if(is_numeric($k)){
				$max=max($max,$k);
			}
		}
		return $max;
	}
	function commitNode(){
		if(count($this->e)<4 || !isset($this->e['n']) || is_null($this->e['n'])){}
		else{
			list($dom_path,$node)=$this->findDomNode($this->e['n']);
			eval($dom_path.' = $this->e;');
		}
		return $this;
	}
	function setTagHTML($html){
		$this->e['html']=$html;
		return $this->commitNode();
	}
	function setTagText($text){
		$this->e['text']=$text;
		return $this->commitNode();
	}
	function addAttr($attr,$value=null){
		if(!$this->e['t']){	return $this; } //text node
		$this->e['a'][$attr]=$value;
		return $this->commitNode();
	}
	function removeAttr($attr){
		if(isset($this->e['a'][$attr])) unset($this->e['a'][$attr]);
		return $this->commitNode();
	}
	
	function addId($value){ return $this->addAttr('id',$value); }
	function removeId($attr){	return $this->addAttr('id'); }
	
	function addClass($value){
		if(!$this->e['t']){	return $this; } //text node
		if(isset($this->e['a']['class']) && $this->e['a']['class']){
			$this->e['a']['class'].=' '.$value; //append
		}
		else{
			$this->e['a']['class']=$value; //init
		}
		return $this->commitNode();
	}
	function removeClass($class){
		if(isset($this->e['a']['class']) && $this->e['a']['class']){
			$this->e['a']['class']=trim(str_replace($class,'',$this->e['a']['class']));
		}
		if(!$this->e['a']['class']){removeAttr('class');}//clear empty class attribute
		return $this->commitNode();
	}
	function renderHtml($domModel=null){
		$html=null;
		if(!$domModel){	$domModel=$this->dom;	}
		if($domModel){
			$html=$this->recurentHtmlRender($domModel,$html);
		}
		return $html;
	}
	function renderParsedHtml($domModel=null){
		$html=null;
		if(!$domModel){	$domModel=$this->domParse; }
		if($domModel){
			$html=$this->recurentHtmlRender($domModel,$html);
		}
		return $html;
	}
	
	//https://codingreflections.com/php-parse-html/
	private function recurentHtmlRender($dom,$startBuffer){
		$spacing='  ';
		$noLineEndTags=array('textarea','pre','cite','title','script','meta','link',);
		//$noLineEndTags=array();
		foreach($dom as $k=>$tag){
			if(!is_numeric($k)){continue;}
			//echo "<h3> {{$tag['t']}} </h3>";
			$tab=$tab2=str_repeat($spacing,substr_count($tag['n'],','));
			$le=$le2="\r\n";
			if($tag['s']===0 && in_array($tag['t'],$noLineEndTags)){
				$tab2='';$le='';
			}
			//if(isset($tag['text']) && $tag['text']){ $tab2=$le=''; }
			if(!isset($tag[0]) && $tag['s']===0){ //no children
				//$tab=$le=$tab2=$le2='';
				$tab2='';$le='';
			}
			
			if(isset($tag[0]['text']) && $tag[0]['text'] && !isset($tag[1])){ //single child text node
				$tab2='';$le='';
			}
			if(	$k==0 && isset($tag['text']) && $tag['text'] && !isset($dom[1])){ //single text node
				$tab=$le=$tab2=$le2='';
			}
			

			
			$startBuffer.=$tab;  //tab tags			
			if($tag['t']){
				$startBuffer.='<'.$tag['t'];
				if($tag['a']){
					foreach($tag['a'] as $attr=>$valA){
						$startBuffer.=' '.$attr;
						if($valA || $valA===''){
							$startBuffer.='="'.htmlentities($valA).'"';
						}
							
					}
				}
				if($tag['s']===1){	$startBuffer.=' /';	}
				$startBuffer.='>'.$le;
			}
			if(isset($tag['html'])){
				$startBuffer.=$le2.$tab.$spacing.$tag['html'].$le2.$tab;
			}
			else{
				if(isset($tag['text']) && $tag['text']){$startBuffer.=$tag['text'].$le2;}
				$startBuffer = $this->recurentHtmlRender($tag,$startBuffer);
			}
			
			if($tag['t']){
				if($tag['s']===0){
//					$startBuffer.='</'.$tag['t'].'>'.$le2;
					$startBuffer.=$tab2.'</'.$tag['t'].'>'.$le2;
					//echo "<h3> {/{$tag['t']}} </h3>";
				}
			}
			
		}
		//echo thfString::h($startBuffer); 		prea($this->dom);die;
		return $startBuffer;
	}
	
	function parseHTML($html){
		//alias nu va fi afectat in teorie
		$this->domParse=array();
		//$this->alias=array();
		$this->domBackup=$this->dom;//save actual dom
		$this->dom=$this->e=array();//clear all buffers
		if(is_string($html)){
			$hlen=strlen($html);
			$openTags=array();
			$node='';
			$attributes=null;
			$text=null;
			
			$insideTagOpen=$insideTagClose=$insideAttr=$insideComment=$insideJS=false;
			$spaceChars=$this->spaceChars; //array(' ',"\r","\n","\t",'>');
			$replaceSpace=					array(' ' ,' ' ,' ' ,' ',' ');
			
			for($pointer=0;$pointer<$hlen;$pointer++){
				
				$chr8=substr($html,$pointer,8);//get next 8 characters  // </script
				$chr7=substr($chr8,0,7);//get next 7 characters
				$chr4=substr($chr8,0,4);//get next 4 characters
				$chr3=substr($chr8,0,3);//get next 3 characters
				$chr2=substr($chr8,0,2);//get next 2 characters
				$chr=substr($chr8,0,1);//get next chr
				//<script src="https://thf.inovativeweb.ro/?jslib=work_efficiency_general&type=js" type="text/javascript"></script>
				
				if($chr4=='<!--' && !$insideJS && !$insideComment && !$insideTagOpen && !$insideTagClose && !$insideAttr){
					$pointer+=3;
					$insideComment=true;
					continue;
				}
				if($chr3=='-->' && $insideComment){
					$pointer+=2;
					$insideComment=false;
					continue;
				}
				
				if($insideJS && strtolower($chr8)=='</script'){
					$insideJS=false;
				}
				
				if($chr==='<' && !$insideJS && !$insideComment && !$insideTagOpen && !$insideTagClose && !$insideAttr){
					$tagBuffer=substr($html,$pointer+1,20);//get the next 20 chars in order to parse the tagname
					$tagBuffer=str_replace($spaceChars,$replaceSpace,$tagBuffer); // replace enter,tab and tag close with simple space in order to allow propper parsing 
					$tagBuffer=explode(' ',$tagBuffer);
					if(trim($tagBuffer[0],'/')){
						$tag=$tagBuffer[0];
						$pointer+=strlen($tag); // <div>x</div>
						if(substr($tag,0,1)=='/'){
							$insideTagClose=true;
							$tag=trim($tag,'/');
						}
						else{
							$insideTagOpen=true;
							$openTags[]=$tag;
							if(trim($text)){
								$this->createTag('')->appendChild($node)->setTagText( trim($text) );
							}
							$text=null;
						}
						
						continue;
					}
					else{} //wrong tag like '< img '  will be skipped
				}
				
				if($insideTagOpen){
					$attributes.=$chr;
				}
				
				if($chr==='>' && !$insideJS && !$insideComment){
					//$px=substr($html,$pointer-8,16);
					//echo "$px\r\n";
					if($insideTagOpen){
						//commit attrs
						$this->createTag($tag);
						$attributes=$this->parseTextAttributes($attributes);
						if(is_array($attributes)){
							foreach($attributes as $attr=>$vattr){
								$this->addAttr($attr,$vattr);
							}
						}
						
						$this->appendChild($node);
						$attributes=null;//clear
						$node=$this->e['n'];//move one level down
						$text=null;
						
						if(strtolower($tag)==='script'){
							$insideJS=true;
						}
						
					}
					if($insideTagOpen && (in_array(strtolower($tag),$this->selfWrapTags1) || in_array(strtolower($tag),$this->selfWrapTags2))){ //array('br','hr','img','!doctype','meta','link');
						$insideTagOpen=false;
						$insideTagClose=true;
					}
					if($insideTagOpen){
						$insideTagOpen=false;
					}
					if($insideTagClose){
						$insideTagClose=false;
						$tag_to_close=array_pop($openTags); //?  $tag===$tag_to_close //to check
						
						if(trim($text)){
							$this->createTag('')->appendChild($node)->setTagText( trim($text) );
						}
						$text=null;
						//move one level up
						$node=explode(',',$node); array_pop($node);
						$node=trim(implode(',',$node),',');
						
					}
					continue;
				}
				
				if(!$insideJS && !$insideComment && !$insideTagOpen && !$insideTagClose && !$insideAttr && strlen($node)){
					$text.=$chr;
				}
				
				
				
			} //end for $pointer
			
			
		}//end if html
		$this->domParse=$this->dom;
		$this->dom=$this->domBackup;//restore actual dom
		$this->e=array();//clear e buffer
		//prea($this->domParse);
		
		return $this;
	}//end function
	
	function parseTextAttributes($attributes){ //http-equiv="content-type" content="text/html; charset=utf-8" 
		$out=array();
		$attributes=rtrim($attributes,'/>');
		$attributes=trim($attributes);
		if($attributes){
			//echo "$attributes \r\n";
			$attributes.=' ';//add one last space in order to wrap things up
			$alen=strlen($attributes);
			$attr=$content=$quot=null;
			for($i=0;$i<$alen;$i++){
				$chr=substr($attributes,$i,1);//get next chr
				$chr2=substr($attributes,$i+1,1);//get next next chr
				$chr3=substr($attributes,$i+2,1);//get next next next chr
				//$this->spaceChars=array(' ',"\r","\n","\t",'>');
				/*
				selected
				selected=""
				selected="selected"
				*/
				if($chr && $chr!='=' && !in_array($chr,$this->spaceChars) && !$content && !$quot ){
					$attr.=$chr;
					continue;
				}
				if($attr && $chr=='=' && !$content && !$quot){//open quot
					$quot=$chr2; $i++;
					if($chr2=$chr3){$content='';}//blank content
					continue;
				}

				if($quot && $chr!=$quot){
					$content.=$chr;
					continue;
				}
				if($quot && $chr===$quot){
					$out[$attr]=$content;
					$attr=$content=$quot=null;
					continue;
				}
				if($attr && in_array($chr,$this->spaceChars) && !$quot){
					$out[$attr]=$content; //blank content
					$attr=$content=$quot=null;
					continue;
				}
				
			}
		}
		return $out;

	}
	
	function attachParsedHtmlToNode($target_node_or_alias){
		//$this->e=$this->domParse;
		//$this->domParse=array();
		//$this->appendChild($target_node_or_alias,'attachParsedHtmlToNode');
		$this->loadNode($target_node_or_alias);
		foreach($this->domParse as $ei){
			$this->e[]=$ei;
		}
		$this->commitNode();
		$this->fixDomNodesAliases();
		return $this;
	}
	

	
	function __construct(){
		$this->createTag('!DOCTYPE')->addAttr('HTML')->appendChild('dom');
		$this->createTag('html')->appendChild('','html')->addAttr('lang','EN');
		$this->createTag('head')->appendChild('html','head');
		$this->createTag('body')->appendChild('html','body');
		$this->createTag('title')->appendChild('head','title')->setTagText('titlu abcdef');
		$this->createTag('div')->appendChild('body','content')->addClass('red');//->setTagHTML('###content###');
		$this->createTag('div')->appendChild('content','content2')->addClass('blue')->setTagHTML('data');
		$this->createTag('')->appendChild('content','txt')->addClass('blue')->setTagText('bla bla');
		
		
		//echo $this->renderHtml();
		//prea($this->dom);	prea($this->alias);
	}
	function __destruct(){}
	
	function setJsonContent($o){
		
	}
	
	
}

<?php


/**
 * @desc			OpenImmo Support Pack 2.0
 * @id:				op_file.php 
 * @package	OI-SPACK
 * @copyright	Copyright (C) 2012 Abcoso Ltd.  All rights reserved.
 * @license		Abcoso SW Usage 2012
 */

class OPfile {

	/**
	 * XML Object from XML File will be stored in Class
	 * @var SimpleXML Object
	 */
	public $XMLdata;	  

	/**
	 * Basic OpenImmo Information form XML File
	 * @var assoc-array
	 */
	public $Xinfo;	 

	protected $RootPath;


	public function __construct() {
	//nothing ..
	}

	/**-------------------------------------------------------------------------
	 * Scan Folder for new Uploads
	 * Start in ftp an loop through all user folders below 
	 * Loops Folder to look for ZIP Files. Stops after first found file !
	 *
	 * @return string FileName 
	 */
	public function GetUpload($path, $prefix="u"){
		
		$info = array();
		$info['file'] = "";
		$info['type'] = "";

		// loop find ZIP or  XML 		
		$vrz = dir($path);  // dir Objekt! 
		
		while ($user = $vrz->Read()){
			$filename = strval($user);
			$p = strlen($filename);
			$ext = strtolower(substr($filename, $p-4, 4));  //	check extension at end of name!
							
			if ($ext == '.zip') {
				// V2: eine Liste ertsllen, dann sort nach datum
				$info['file'] = $filename;
				$info['type'] = "zip";
				break 1;	// end all loops !
			}//if

			if ($ext == '.xml') {
				// V2: eine Liste ertsllen, dann sort nach datum
				$info['file'] = $filename;
				$info['type'] = "xml";
				break 1;	// end all loops !
			}//if
		}//wend    
		
		$vrz->close();

		return $info;
	}//fkt 

	/**-------------------------------------------------------------------------
	 *  
	 *  Extracting a ZIP File
	 * @param string FileName
	 * Result = XML file and optional Pictures
	 * @return stat
	 */
	public function ExtractZIP($fileinfo){

		$zf = $fileinfo['folder'] . $fileinfo['file'];
		
		$tp = $fileinfo['temp'] ;
		
		$stat = "";
		$zip = new ZipArchive;
		
		if ($zip->open($zf) == TRUE) {
			
			$zip->extractTo($tp);
			
			$vrz = dir($fileinfo ['temp']);
			while ($child = $vrz->Read()){
				$po = stripos($child , '.xml');
				if ($po > 0) { 
					$stat = $child; 
					
					break;
				}//if
			}//while
		} else { //open=false
			$stat = "";
		}

		$zip->close();
		$zip = null;

		return $stat;
	}//dozip

	/** -------------------------------------------------------------------------
	 * LOAD XML FILE using simpleXML Lib
	 *
	 * @param string xmlfilename
	 * @internal  sets XMLinfo Array
	 */
	public function LoadXML($fname){
		// Return the XML DOM Object for further use 
		$fna = $fname;
		
		$stat = true;
		
		// pase XML ...
		if (file_exists($fna)) {
			$this->XMLdata = @simplexml_load_file($fna);   //xml ist die Root

			if ( $this->XMLdata == false) {
				$stat = false;
			}//if
		}else {
			$this->XMLdata = NULL;
			$stat = false;
		}//if
		
		if ($stat == true) {
			// scan encoding via text (no other way with simplexml )
			$fin = fopen($fna, 'r');
			
			$line = fgets($fin, 200);
			fclose($fin);
			$p = strpos($line, "encoding=");
			
			if ($p > 0) { 
				$this->Xinfo['enc'] = strtolower($line[$p+10]);
			}else {
				// default = UTF-8
				$this->Xinfo['enc'] = "u";
			}//if
		}//if

		return $stat;
	}//fkt

	/** -------------------------------------------------------------------------
	 * Extract Basic Information OpenImmo holds
	 * i.e. Namespace, Encoding,Sender  SW ....
	 *
	 * @internal sets XMLinfo Arr 
	 */
	public function XMLinfo(){

		$nasp = '';  //quote change
		$nasu = '';
		$nasp1 = '';

		//-------------- namespace -----------------
		$namespaces = $this->XMLdata->getNamespaces(true);

		// remove XSI und XSD 
		if (!empty($namespaces['xsi'])) {
			unset($namespaces['xsi']);
		}//if

		if (!empty($namespaces['xsd'])) {
			unset($namespaces['xsd']);
		}//if

		if (empty($namespaces)) {
			// reset namespace
			$this->XMLdata->registerXPathNamespace('', '');

		} else {
			// setzte einen festen Namespace 
			$nasu = "";
			foreach ($namespaces as $nsp => $value) {
				// dbg2("Namespace check ",$value );
				if ($value == 'http://www.openimmo.de') { $nasu = "http://www.openimmo.de"; } 
				if ($value == 'http://www.immoxml.de') { $nasu = "http://www.immoxml.de"; } 
			}//for

			$nasp = "op:";
			$nasp1 = "op";
			
			$this->XMLdata->registerXPathNamespace('op', $nasu);
		} //endif

		$this->Xinfo['nsp'] = $nasp;
		$this->Xinfo['nsps'] = $nasu;

		// nodeliste = list of all immo elements
		$xpa = "//" . $nasp . "immobilie";
		
		$nodeList = $this->XMLdata->xpath($xpa);
		$this->Xinfo['anzimmo'] = count($nodeList);

		$xpa = "//" . $nasp . "uebertragung";
		$nl = $this->XMLdata->xpath($xpa);
		$att = $nl[0]->attributes();

		$this->Xinfo['ue'] = $att['art'];
		$this->Xinfo['umfang'] = $att['umfang'];
		$this->Xinfo['sw'] = strtolower($att['sendersoftware']); 
		$this->Xinfo['swk'] = substr(strtolower($att['sendersoftware']), 0, 4);    

		// ------------ der d...  aus kassel -----------
		if ($this->Xinfo['sw'] == 'xx'){
			$this->Xinfo['version'] = "0.1";
		}else{
			$this->Xinfo['version'] = "1.0";
		}//if
		
		// infos about anbieter name and id
		$xpa = "//" . $nasp."anbieter";
		$nl = $this->XMLdata->xpath($xpa);
		$this->Xinfo['anzanbieter'] = count($nl);

		$xpa = "//" . $nasp . "anbieter/" . $nasp . "firma";
		$nl = $this->XMLdata->xpath($xpa);
		if (!$nl[0] == null) {
			$this->Xinfo['anbietername'] = $nl[0];
		}else {
		$this->Xinfo['anbietername'] = "";
		}

		$xpa = "//" . $nasp . "anbieter/" . $nasp . "openimmo_anid";
		$nl = $this->XMLdata->xpath($xpa);
		if ($nl[0] != null) {
			$this->Xinfo['anbieterid'] = $nl[0];
		}else {
		$this->Xinfo['anbieterid'] = "";
		}

		return $this->Xinfo;
	}//fkt

	/** ---------------------------------------------------------------------------------
	 * Read one <immobilie> into 2-dim assoc array
	 * conv <element and attributes  in 3 rd array 
	 * Xpath Query need Namespace
	 * 
	 * @param string nsp=Namespace prefix, nsps= Namespace-String
	 * @return 
	 */
	function GetOneImmo($ni , $xinfo){

		$nsp = $this->Xinfo['nsp'] ;
		$nsps = $this->Xinfo['nsps'];
		$enc = $this->Xinfo['enc'];
		// $xml=$this->XMLdata;

		$xpa = "//" . $nsp . "immobilie[" . $ni . "]";
		// Exec Xpath String on the XML Object in OPIMFILE Class

		$nl = $this->XMLdata->xpath($xpa); // NL= NodeList !
		
		if (count($nl) >0) {
			
			$immo = $nl[0];
			$immo->getName() =='immobilie';
			$ok = true;
		}else{
			$ok = false;
		}//if

		$attarray = array();
		$imgcount = 0;
		
		if ($ok == true) {
			
			// --------- bereiche geo, preis ... -------------------
			foreach ($immo->children($nsps) as $strukt) {

				$sname = $strukt->getName();
				$ecount = 0;

				//---------- die elemente ------------------
				foreach ($strukt->children($nsps) as $element) {
					
					// die eigentlichen Daten Elemente
					$ename = $element->getName();
					$val = strval($element);
			
					$ecount++;
					
					// ---------- copy all attributes to array
					$attr = $element->attributes();
					
					$attarray = null;
					if (count($attr) > 0) {
						$attarray['_val'] = $val;  //element value

						foreach($element->attributes() as $a => $b) {	

							$attarray[strval($a)] = strval($b) ;
						}//for
					}//if

					
					if ($ename  == 'objektart' ) {  
						//XML z.B. <objektart><haus haus="VILLA"/>..
						// put <haus + attribute in  attarray[name] and attayrr[type]
						$oa = $element->children($nsps);
						if (!empty($oa)) {
							$att = $oa->attributes();
							// add a second element 
							$idata[$sname]['objektart2'] = strval($att[0]);
							$val = $oa->getName();
						}//if
					}//if			

					if ($ename  == 'anhang') {  
						//XML sample: <anhang location="EXTERN" gruppe="INNENANSICHT"><anhangtitel>Bild1</anhangtitel><format>JPG</format><daten><pfad>1913481.jpg</pfad>
						
						$idata[$sname]["anh".$imgcount]['gruppe'] = $attarray['gruppe'];
						
						// check all children under <anhang>
						foreach ($element->children($nsps) as $item) {
							$na = $item->getName();
							$va = strval($item);
							switch ($na)					{
								// case 'anhangtitel': $idata[$sname]["anh".$imgcount]['titel'] = utf8_decode($va);
								case 'anhangtitel': 
									$idata[$sname]["anh".$imgcount]['titel'] = $va;
									break;
								case 'format': 
									$idata[$sname]["anh".$imgcount]['format'] = $va;
									break;
								case 'daten': 
									$file = $item->children($nsps);

									$idata[$sname]["anh".$imgcount]['datei'] = strval($file);
									break;
							}//sw
						}//for
						$attarray = null;
						$imgcount++;
						$ename = "";  // do not add the anhaenge "content" as element

					}//if			

					if ($ename  == 'foto') {  
						$foto = $element->children($nsps);

						if (! $foto == null) {
							
							foreach($attr as $attrKey => $attrValue){
								$idata[$sname][$ename][$attrKey] = strval($attr[0]);
							}
							// add a second element 
							$idata[$sname][$ename]['format'] = strval($foto->format);
							$idata[$sname][$ename]['datei'] = strval($foto->daten->pfad);
						}//if

						$attarray = null;
						$ename = '';
					}//if

					//   User defined Fields ...
					if ($ename  == 'user_defined_simplefield') {  
						//<user_defined_simplefield feldname="xx">Wert</..
						$ename ="UDS_" . $attarray['feldname'];
						$attarray = null;
					}//if			

					if ($ename  == 'user_defined_anyfield') {  
						//<user_defined_anyfield > beleibige struktur .... rekursiv in string or as XML string..
						$ename = "UDA" . $ecount . ":";
					}//if			


					if ($ename  == 'user_defined_extend') {  
						//user_defined_extend  <feld....>* 
						$ename = "UDE" . $ecount . ":";
						// loop over all foield <feld name="f001" type="t001">Wert</feld>
						$x = $element->children();
						$val = $x->asXML();
					}//if			
					if (!empty($ename)) {

						// 2DO:  Check on SQL injection, Quotes, html striptags ...

						if ($attarray == null) {
							// element ohne attrib

							if($element->children($nsps) && $ename == 'energiepass'){     // Check element children and name
								$idata[$sname][$ename] = [];

								foreach($element->children($nsps) as $elem){

									// die eigentlichen Daten Elemente
									$ename1=$elem->getName();
									$val1=strval($elem);
									
									if (!empty($ename1)) {
										// 2DO:  Check on SQL injection, Quotes, html striptags ...
										// element ohne attrib

										$idata[$sname][$ename][$ename1] = strval($val1);
									}//if
								}
							}else{
								$idata[$sname][$ename] = str_replace (chr(39), "\'", strval($val));
							}
						}else{
							$idata[$sname][$ename] = $attarray;
						}//if
					}//if
				}//for elem
			}//for strukt 

		} //if immo

		return $idata;
	}//fkt

	/**
	 * Extract the Image Infos from the xml /array
	 *
	* @param string ai= Anbiter Index , oi = Index of <immobilie>  xo= Object Handle for XMLdate
	* @return assoc array 
	*/
	public function GetImgInfo($idata, $onr) {
	
		$imglist = array();
		$im = 0;
		$imgformat ="jpg,jpeg,pjpg,pjpeg,png,gif"; // blank wg strpos
		
		if (gettype($idata['anhaenge']) == 'array') {

			//--------- loop through die <anhang> Elemente --------------
			foreach ($idata['anhaenge'] as $anhang){

				//anhang = array(4) { ["gruppe"]  ["titel"] ["format"]["datei"] } 
				$imglist[$im]['gruppe'] = strval($anhang['gruppe']);
				$imglist[$im]['titel'] = strval($anhang['titel']);
				$imglist[$im]['xdatei'] = strval($anhang['datei']);  // original XML Datei
				// je nach SW Vendor auch aus userdefined ..
				$imglist[$im]['tmpdatei'] = $imglist[$im]['xdatei'];  // original XML Datei, optional change in Fetch..

				
				// file format form xml und extension
				$imglist[$im]['xformat'] = strval($anhang['format']);
				$p = strrpos($imglist[$im]['xdatei'], ".");  // last!! "." position 
				$imglist[$im]['dformat'] = strtolower(substr($imglist[$im]['xdatei'], $p+1));

				// pdatei=no extension, bdatei=medium size,  tdatei =Thumbnail	 (Original)
				$imglist[$im]['pdatei'] = substr($imglist[$im]['xdatei'], 0, $p-1);  // filename ohne extension

				if (strpos($imgformat,  $imglist[$im]['dformat']) > 0) {
					// it ist jpg gif or aother image format
					$imglist[$im]['isimg'] = true;
				}else{
					// is other format 
					$imglist[$im]['isimg'] = false;
				}//if

				// Sample for building the Image File Names 
				// bdatei= Basic Image,  tdatei= Thumbnail ,  sdatei = Simple name without Extension

				$imglist[$im]['bdatei'] = "im" . $onr . "-" . $im . "_b." . $imglist[$im]['dformat'];
				$imglist[$im]['tdatei'] = "im" . $onr . "-" . $im . "_t." . $imglist[$im]['dformat'];
				$imglist[$im]['sdatei'] = "im" . $onr . "-" . $im;
				$imglist[$im]['status'] = 1;

				$im++;
			}//for 
		}//if

		return $imglist;
	}//fkt

	/** -------------------------------------------------------------------------
	 * Helper.  For Extracting the Xpath Query with internal XML Data
	 *
	 * @param string Xpath Query
	 * @return  Nodelist with XML Objects
	 */
	public function GetXpath($xpa ){

		$nl = $this->XMLdata->xpath($xpa);  
		return $nl;
	}//fkt

	public function GetTheRoot() {
		$r = $_SERVER['DOCUMENT_ROOT'];

		if (substr($r,strlen($r)-1, 1) != '/') { $r = $r . "/"; }
		$this->RootPath = $r;
		return $r;
	}

	//------------------------------------------------------------
	public function decodeUTF8($data) {
		foreach ($data as $name=>$value) {
			$data[$name] = utf8_decode($value) ;
		}
		return $data;
	}//fkt

}//class 

?>
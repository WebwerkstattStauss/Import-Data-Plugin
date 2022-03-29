<?php

/**
 * @desc			XML Import  		
 * @version		2.0, 04.04.2012 
 * @package		OpenImmo Support Pack 2.0 
 * @subpackage	Modules
 * @copyright	Copyright (C) 2012 Abcoso Ltd.  All rights reserved.
 * @license		Abcoso SW Usage 2012
 */


 include "op_file.php";
 include "op_tools.php";

 

/* *******************************************
					Aufrufen der zentralen Routine 
 ******************************************* */

readXML();


//********************************************



/** -----------------------------------------------------------------
* @desc	Finde XML/ZIP, entpacken und einlesen der daten
*/

 function readXML()  { 

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$opfile = new OPfile();
	session_start();

	// Get main root path
	$ropa = $opfile->GetTheRoot();		//opfile
	
	// Set inner folder path
	$ropa = $ropa . "proj/opack20/";
	
	// Set the folder path where the zip file is stored
	$origFolderFrom = $ropa . "ftpin/";
	
	// Set the folder path where the zip file will be stored after the extract
	$ftpTemp = $ropa . "ftpin/temp/";

	// Set the folder path where the imported zip file will be stored
	$oldImportedZip = $ropa."oldftpin/";
	
	// Find the ZIP/XML File in the FTPIN Folder 
	// $fileInfo will be used by following functions. you can add more information if needed
	$fileInfo = $opfile->GetUpload($origFolderFrom);  // return = assoc-array     // opfile 

	$fileInfo['temp'] = $ftpTemp;
	$fileInfo['folder'] = $origFolderFrom ;
	
	// Extract or Copy the Files, so we can prozess the xml info
	$xmlFileName = '';
	if ($fileInfo['type'] == 'zip') {
		// if it is ZIP, extract it to temp	
		$xmlFileName = $opfile->ExtractZIP($fileInfo);     // opfile
	}else if ($fileInfo['type'] == 'xml'){
		// if it is XML, copy it to temp 
		$xmlFileName = $fileInfo['file'];
		
		if(!empty($xmlFileName)){
			copy($fileInfo['folder'].$xmlFileName, $ftpTemp.$xmlFileName);
		}
	}//if

	// XML File from temp folder 
	$stat = (!empty($xmlFileName)) ? $opfile->LoadXML($ftpTemp.$xmlFileName) : false;     // opfile
	
	// you can access the XML Object by:  $opfile ->XMLdata;  
	
	if ($stat == true) {
		// read basic infos from XML File 
		$xinfo= $opfile->decodeUTF8($opfile->XMLinfo());
	}else {
		// Error in Processing. Stop the run.
		$xinfo['anzanbieter'] = 0 ;
		$xinfo['anzimmo'] = 0;
	}//if

	$azi = intval($xinfo['anzimmo']);

	for ($ni = 1; $ni <= $azi; $ni++) {  // $xinfo['anzimmo']
		
		/* ================ CONVERT XML TO ARRAY  ==================== */
		// idata = 2 Dim Assoc Array [area][element]  from <immobile> Elements
		$idata = $opfile->GetOneImmo( $ni, $xinfo);
		
		// $postName = convert_symbol($idata['freitexte']['objekttitel']);

		$postName = (isset($idata['freitexte']['objekttitel'])) ? $idata['freitexte']['objekttitel'] : '';
		$postTitle = (isset($idata['freitexte']['objekttitel'])) ? $idata['freitexte']['objekttitel'] : '';
		$postContent = (isset($idata['freitexte']['objektbeschreibung'])) ? $idata['freitexte']['objektbeschreibung'] : '';

		if(isset($idata['zustand_angaben']['verkaufstatus']) && is_array($idata['zustand_angaben']['verkaufstatus'])){
			$postStatusGet = (isset($idata['zustand_angaben']['verkaufstatus']['stand'])) ? $idata['zustand_angaben']['verkaufstatus']['stand'] : '';
		}else{
			$postStatusGet = '';
		}

		$postStatusSet = 'publish';

		// Get and Set Post-Attr
		$postAttrId = (isset($idata['verwaltung_techn']['objektnr_extern'])) ? $idata['verwaltung_techn']['objektnr_extern'] : '';
		$postAttrIdFio = (isset($idata['verwaltung_techn']['objektnr_intern'])) ? $idata['verwaltung_techn']['objektnr_intern'] : '';
		$postAttrAreaSize = (isset($idata['flaechen']['wohnflaeche'])) ? $idata['flaechen']['wohnflaeche'] : '';
		$postAttrLotSize = (isset($idata['flaechen']['grundstuecksflaeche'])) ? $idata['flaechen']['grundstuecksflaeche'] : '';
		// $postAttrLotSize = (isset($idata['flaechen']['anzahl_stellplaetze'])) ? $idata['flaechen']['anzahl_stellplaetze'] : '';     
		$postAttrBedrooms = (isset($idata['flaechen']['anzahl_zimmer'])) ? $idata['flaechen']['anzahl_zimmer'] : '';
		$postAttrPricePostfix = 'pro Monat';
		$postAttrAreaSizePostfix = 'm²';
		$postAttrLotSizePostfix = 'm²';
		$postAttrDetails = (isset($idata['ausstattung'])) ? $idata['ausstattung'] : '';

		// Get and Set Post-ATTR-Property-Address
		$postAttrPropertyAddress = (isset($idata['geo']['plz'])) ? $idata['geo']['plz'] . ' ' : '';
		$postAttrPropertyAddress .= (isset($idata['geo']['ort'])) ? $idata['geo']['ort'] . ', ' : '';
		$postAttrPropertyAddress .= (isset($idata['geo']['bundesland'])) ? $idata['geo']['bundesland'] . ', ' : '';
		$postAttrPropertyAddress .= (isset($idata['geo']['land']['iso_land'])) ? $idata['geo']['land']['iso_land']  : '';


		// Get and Set Post-ATTR-Agent
		$postAttrAgent = [];
		if (array_filter($idata['kontaktperson'])) {
			$postAttrAgent['email_centr']  = (isset($idata['kontaktperson']['email_zentrale'])) ? $idata['kontaktperson']['email_zentrale'] : '';
			$postAttrAgent['email_direkt'] = (isset($idata['kontaktperson']['email_direkt'])) ? $idata['kontaktperson']['email_direkt'] : '';
			$postAttrAgent['tel_durchw'] = (isset($idata['kontaktperson']['tel_durchw'])) ? $idata['kontaktperson']['tel_durchw'] : '';
			$postAttrAgent['tel_fax'] = (isset($idata['kontaktperson']['tel_fax'])) ? $idata['kontaktperson']['tel_fax'] : '';
			$postAttrAgent['tel_handy'] = (isset($idata['kontaktperson']['tel_handy'])) ? $idata['kontaktperson']['tel_handy'] : '';
			$postAttrAgent['name'] = (isset($idata['kontaktperson']['vorname'])) ? $idata['kontaktperson']['vorname'] . ' ' : '';
			$postAttrAgent['name'] .= (isset($idata['kontaktperson']['name'])) ? $idata['kontaktperson']['name'] : '';
			$postAttrAgent['firma'] = (isset($idata['kontaktperson']['firma'])) ? $idata['kontaktperson']['firma'] : '';
			$postAttrAgent['url'] = (isset($idata['kontaktperson']['url'])) ? $idata['kontaktperson']['url'] : '';
			$postAttrAgent['photo'] = (isset($idata['kontaktperson']['foto']['datei'])) ? $idata['kontaktperson']['foto']['datei'] : '';
		}

		// The action from openimmo: ADD, CHANGE, DELETE 
		$action = $idata['verwaltung_techn']['aktion']['aktionart'];

		if ($action == '') {
			$action = "ADD";
		}
		
		//  Generate an Obj ID for your datenbase
		// You can also use i.e.  idata ['verwaltung_techn']['openimmo_obid'] = ID form the Software

		if ($postStatusGet != 'OFFEN') {
			$postStatusSet = 'private';
		}

		if ($action == 'ADD') {

			// Check postname exists
			$checkPostName = get_page_by_title($postName, 'OBJECT', array('property', 'post', 'page'));

			if (!empty($checkPostName)) {
				$postName = $postName . '_' . rand(1, 200);
			}

			// basic post data
			$wpPostData = array(
				'post_type' => 'property',
				'post_title' => $postTitle,
				'post_content' => $postContent,
				'post_name' => $postName,
				'post_status' => $postStatusSet,
				'ping_status' => 'closed',
				'post_author' => 1,
			);

			//insert post
			$postId = createPost($wpPostData);   // op_tools

		}
		else{
			// find the Objekt in your Datenbase, if it ist CHANGE oder DELETE
			
			//check exist property id
			$args = array(
				'posts_per_page' => -1,
				'post_type' => 'property',
				'meta_key' => 'REAL_HOMES_property_id',
				'meta_value' => $postAttrId,
			); 
			$existPostsByPropertyId = get_posts($args);

			//check exist property id fio
			$args1 = array(
				'posts_per_page' => -1,
				'post_type' => 'property',
				'meta_key' => 'REAL_HOMES_property_id',
				'meta_value' => $postAttrIdFio,
			);
			$existPostsByFioId = get_posts($args1);

			$postId = '';

			// Set post-id
			if (!empty($existPostsByPropertyId)) {
				$postId = $existPostsByPropertyId[0]->ID;
			} else if(!empty($existPostsByFioId)) {
				$postId = $existPostsByFioId[0]->ID;
			}

			if ($action == 'CHANGE') {

				// basic update post data
				$wpPostData = array(
					'ID' => $postId,
					'post_title' => $postTitle,
					'post_content' => $postContent,
					'post_name' => $postName,
					'post_status' => $postStatusSet,
				);

				// update post
				updatePost($postId, $wpPostData);   // op_tools
				
			}//if


			if ($action == 'DELETE') {
				// delete your Entry, or mark it as "offline"
				deletePost($postId);   // op_tools
			}//if

		}//if

		//post main image
		$postMainImageTitle = (isset($idata['anhaenge']['anh0']['titel'])) ? $idata['anhaenge']['anh0']['titel'] : '';
		$postMainImageName = (isset($idata['anhaenge']['anh0']['datei'])) ? $idata['anhaenge']['anh0']['datei'] : '';

		//insert post main image
		if (file_exists($ftpTemp . $postMainImageName) && !is_dir($ftpTemp . $postMainImageName)) {
			my_sideload_image_new($postId, $postMainImageName, $ftpTemp, $postMainImageTitle);   // op_tools
		}

		//post gallery images
		$postGalleryImages = (isset($idata['anhaenge'])) ? $idata['anhaenge'] : [];

		//insert post images gallery
		if (array_filter($postGalleryImages)) {

			foreach ($postGalleryImages as $key => $galleryImgData) {

				$fileName = $galleryImgData['datei'];
				$galleryImageTitle = $galleryImgData['titel'];

				if ($postMainImageName == $fileName) {
					continue;
				}

				if (strpos($fileName, '.pdf') != false) {
					$galleryImgId = my_sideload_image_new(0, $fileName, $ftpTemp, $galleryImageTitle, true, false);   // op_tools
					ws_add_meta($postId, 'REAL_HOMES_attachments', $galleryImgId);   // op_tools
				} else {
					$galleryImgId = my_sideload_image_new(0, $fileName, $ftpTemp, $galleryImageTitle, true);   // op_tools

					add_post_meta($postId, 'REAL_HOMES_property_images', $galleryImgId);
				}
			}
		}

		// post price
		$postPrice = (isset($idata['preise']['kaufpreis'])) ? $idata['preise']['kaufpreis']['_val'] : '';
		$postPriceRent = (isset($idata['preise']['warmmiete'])) ? $idata['preise']['warmmiete']['_val'] : '';

		// post tag
		$postTagRent = (isset($idata['objektkategorie']['vermarktungsart']['MIETE_PACHT'])) ? $idata['objektkategorie']['vermarktungsart']['MIETE_PACHT'] : false;
		$postTagBuy = (isset($idata['objektkategorie']['vermarktungsart']['KAUF'])) ? $idata['objektkategorie']['vermarktungsart']['KAUF'] : false;

		// add cat rent
		if ($postTagRent == 'true') {
			$termArray = [];

			$getTerm = get_term_by('slug', 'for-rent', 'property-status');
			$termArray[] = $getTerm->term_id;
			wp_set_post_terms($postId, $termArray, 'property-status');
			
			//set rent price
			ws_add_meta($postId, 'REAL_HOMES_property_price', $postPriceRent);  // op_tools
			ws_add_meta($postId, 'REAL_HOMES_property_price_postfix', $postAttrPricePostfix);   // op_tools

		}

		// add cat buy
		if ($postTagBuy == 'true') {
			$termArray = [];

			$getTerm = get_term_by('slug', 'for-sale', 'property-status');
			$termArray[] = $getTerm->term_id;
			wp_set_post_terms($postId, $termArray, 'property-status');
			
			//set by price
			ws_add_meta($postId, 'REAL_HOMES_property_price', $postPrice);   // op_tools
		}

		$postTagCity = (isset($idata['geo']['ort'])) ? $idata['geo']['ort'] : '';
		$postTagPropery = (isset($idata['ausstattung']['ausstatt_kategorie'])) ? $idata['ausstattung']['ausstatt_kategorie'] : '';

		// Get and Set Parent-Tag-Slug and Post-Tag-Property-Types
		$parentTagSlug = '';

		if (isset($idata['objektkategorie']['objektart'])) {
			if ($idata['objektkategorie']['objektart'] == 'zimmer') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'zimmer';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'wohnung') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'wohnung';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'haus') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'haus';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'grundstueck') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'grundstueck';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'buero_praxen') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'buero_praxen';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'einzelhandel') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'einzelhandel';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'gastgewerbe') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'gastgewerbe';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'freizeitimmobilie_gewerblich') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'freizeitimmobilie_gewerblich';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'zinshaus_renditeobjekt') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'zinshaus_renditeobjekt';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'hallen_lager_prod') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'hallen_lager_prod';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'land_und_forstwirtschaft') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'land_und_forstwirtschaft';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'sonstige') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						$postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$parentTagSlug = 'sonstige';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			} else if ($idata['objektkategorie']['objektart'] == 'zinshaus_renditeobjekt') {
				if (isset($idata['objektkategorie']['objektart2'])) {
					try {
						// $postTagPropertyTypes = $idata['objektkategorie']['objektart2'];
						$postTagPropertyTypes = 'wohn-und-geschaeftshaus';
						$parentTagSlug = 'zinshaus_renditeobjekt';
					} catch (exception $e) {
						$postTagPropertyTypes = false;
					}
				}
			}
		}

		//add cat city
		if (!empty($postTagCity)) {
			$getTerm = get_term_by('name', $postTagCity, 'property-city');
			
			if ($getTerm != false) {
				wp_set_post_terms($postId, $getTerm->term_id, 'property-city');
			} else {

				/////////
				if (!taxonomy_exists('property-city')) {
					register_taxonomy(
						'property-city',
						'property',
					);
				}
				/////////

				$newCityTerm = wp_insert_term($postTagCity, 'property-city');

				wp_set_post_terms($postId, $newCityTerm['term_id'], 'property-city');
			}
		}

		//add cat property features
		if (!empty($postTagPropery)) {
			$getTerm = get_term_by('name', $postTagPropery, 'property-feature');
			if ($getTerm != false) {
				wp_set_post_terms($postId, $getTerm->term_id, 'property-feature');
			} else {
				
				/////////
				if (!taxonomy_exists('property-feature')) {
					register_taxonomy(
						'property-feature',
						'property',
					);
				}
				/////////

				$newPropertyTerm = wp_insert_term($postTagPropery, 'property-feature');
				wp_set_post_terms($postId, $newPropertyTerm['term_id'], 'property-feature');
			}
		}

		//add cat property types
		if (!empty($postTagPropertyTypes)) {

			$termAlias = strtolower($postTagPropertyTypes);
			$getTerm = get_term_by('slug', $termAlias, 'property-type');
			if ($getTerm) {
				$termsId = array();
				array_push($termsId, $getTerm->term_id);

				if ($getTerm->parent != '0') {
					$getParentTerm = get_term_by('term_taxonomy_id', $getTerm->parent, 'property-type');
					if ($getParentTerm) {
						array_push($termsId, $getParentTerm->term_id);
					}
				}

				wp_set_post_terms($postId, $termsId, 'property-type');
			}else{

				$arrayPropertyTypes = json_decode(json_encode($postTagPropertyTypes), true);
				$newTermItemSlug = strtolower($arrayPropertyTypes[0]);
				$newTermItemName = ucfirst($newTermItemSlug);
				$newTermItemName = str_replace('_', ' ', $newTermItemName);

				$checkParentTag = get_term_by('slug', $parentTagSlug, 'property-type');

				/////////
				if (!taxonomy_exists('property-type')) {
					register_taxonomy(
						'property-type',
						'property',
					);
				}
				/////////

				if($checkParentTag){
					$termsId = array();
					array_push($termsId, $checkParentTag->term_id);

					//create new term item
					$newPropertyObj = wp_insert_term($newTermItemName, 'property-type', array(
						'description' => '',
						'parent' => $checkParentTag->term_id,
						'slug' => $newTermItemSlug,
					));

					if($newPropertyObj){
						array_push($termsId, $newPropertyObj['term_id']);
					}

					wp_set_post_terms($postId, $termsId, 'property-type');
				}else{

					$termsId = array();

					//create new parent term item
					$parentTagName = ucfirst($parentTagSlug);
					$newParentPropertyObj = wp_insert_term($parentTagName, 'property-type', array(
						'description' => '',
						'slug' => $parentTagSlug,
					));

					if($newParentPropertyObj){
						array_push($termsId, $newParentPropertyObj['term_id']);

						//create new sub term item
						$newPropertyObj = wp_insert_term($newTermItemName, 'property-type', array(
							'description' => '',
							'parent' => $newParentPropertyObj['term_id'],
							'slug' => $newTermItemSlug,
						));

						if($newPropertyObj){
							array_push($termsId, $newPropertyObj['term_id']);
						}

						wp_set_post_terms($postId, $termsId, 'property-type');
					}
				}
			}
		}

		//add post meta
		ws_add_meta($postId, 'REAL_HOMES_property_size', $postAttrAreaSize);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_lot_size', $postAttrLotSize);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_bedrooms', $postAttrBedrooms);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_size_postfix', $postAttrAreaSizePostfix);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_lot_size_postfix', $postAttrLotSizePostfix);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_address', $postAttrPropertyAddress);   // op_tools

		$parkingSpaceRent = "";
		$parkingSpaceNumber = "";
		$parkingSpaceSeles = "";
		
		if (isset($idata['preise']['stp_tiefgarage']) && is_array($idata['preise']['stp_tiefgarage'])) {
			$parkingSpaceRent = (isset($idata['preise']['stp_tiefgarage']['stellplatzmiete'])) ? json_decode($idata['preise']['stp_tiefgarage']['stellplatzmiete']) : '';
			$parkingSpaceNumber = (isset($idata['preise']['stp_tiefgarage']['anzahl'])) ? json_decode($idata['preise']['stp_tiefgarage']['anzahl']) : '';
			$parkingSpaceSeles = (isset($idata['preise']['stp_tiefgarage']['stellplatzkaufpreis'])) ? json_decode($idata['preise']['stp_tiefgarage']['stellplatzkaufpreis']) : '';
		}

		ws_add_meta($postId, 'REAL_HOMES_property_stellplatzmiete_price', $parkingSpaceRent);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_stellplatzmiete_number', $parkingSpaceNumber);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_property_stellplatzmiete_sales_price', $parkingSpaceSeles);   // op_tools

		//energy
		$epart = (isset($idata['zustand_angaben']['energiepass']['epart'])) ? $idata['zustand_angaben']['energiepass']['epart'] : '';
		$gueltig = (isset($idata['zustand_angaben']['energiepass']['gueltig_bis'])) ? $idata['zustand_angaben']['energiepass']['gueltig_bis'] : '';
		$evb = (isset($idata['zustand_angaben']['energiepass']['endenergiebedarf'])) ? $idata['zustand_angaben']['energiepass']['endenergiebedarf'] : '';
		$primaeret = (isset($idata['zustand_angaben']['energiepass']['primaerenergietraeger'])) ? $idata['zustand_angaben']['energiepass']['primaerenergietraeger'] : '';
		$wertklasse = (isset($idata['zustand_angaben']['energiepass']['wertklasse'])) ? $idata['zustand_angaben']['energiepass']['wertklasse'] : '';
		$baujahr = (isset($idata['zustand_angaben']['energiepass']['baujahr'])) ? $idata['zustand_angaben']['energiepass']['baujahr'] : '';
		$ausstelldatum = (isset($idata['zustand_angaben']['energiepass']['ausstelldatum'])) ? $idata['zustand_angaben']['energiepass']['ausstelldatum'] : '';
		$jahrgang = (isset($idata['zustand_angaben']['energiepass']['jahrgang'])) ? $idata['zustand_angaben']['energiepass']['jahrgang'] : '';
		$gebaeudeart = (isset($idata['zustand_angaben']['energiepass']['gebaeudeart'])) ? $idata['zustand_angaben']['energiepass']['gebaeudeart'] : '';
		$epasstext = (isset($idata['zustand_angaben']['energiepass']['epasstext'])) ? $idata['zustand_angaben']['energiepass']['epasstext'] : '';

		//add post meta energy
		ws_add_meta($postId, 'REAL_HOMES_epart', $epart);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_gueltig', $gueltig);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_energy_performance', $evb);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_primaeret', $primaeret);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_energy_class', $wertklasse);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_baujahr', $baujahr);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_ausstelldatum', $ausstelldatum);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_jahrgang', $jahrgang);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_gebaeudeart', $gebaeudeart);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_epasstext', $epasstext);   // op_tools


		//attr
		$courtage = (isset($idata['preise']['aussen_courtage'])) ? $idata['preise']['aussen_courtage']['_val'] : '';
		$courtageNotice =(isset($idata['preise']['courtage_hinweis'])) ? $idata['preise']['courtage_hinweis'] : '';

		$courtageIsTaxed = false;
		if (isset($idata['preise']['aussen_courtage'])) {
			$courtageTax = (isset($idata['preise']['aussen_courtage']['mit_mwst'])) ? $idata['preise']['aussen_courtage']['mit_mwst'] : '';
			if ($courtageTax == 'true') {
				$courtageIsTaxed = true;
			}
		}
		unset($courtageTax);

		if ($courtageIsTaxed && strpos($courtage, '%') !== false) {
			$courtage .= ' inkl. MwSt.';
		} elseif (strpos($courtage, '%') !== false) {
			$courtage .= ' zuzügl. MwSt.';
		}
		unset($courtageIsTaxed);
		
		if ($postTagRent == 'true') {
			$courtage = 'Keine Angabe zur Vermietercourtage';
		}

		ws_add_meta($postId, 'REAL_HOMES_courtage', $courtage);   // op_tools
		ws_add_meta($postId, 'REAL_HOMES_courtage_notice', $courtageNotice);   // op_tools

		//add property_id
		delete_post_meta($postId, 'REAL_HOMES_property_id', '');
		update_post_meta($postId, 'REAL_HOMES_property_id', $postAttrId);

		//add geo kordinates
		$googleApiKey = get_option('inspiry_google_maps_api_key');
		$address = urlencode($postAttrPropertyAddress);
		$urlGoogleApi = "https://maps.googleapis.com/maps/api/geocode/json?address=" . $address . "&key=" . $googleApiKey;
		$curl = curl_init($urlGoogleApi);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$curlResponse = curl_exec($curl);
		curl_close($curl);
		$curlResponse = json_decode($curlResponse, true);

		$lat = $curlResponse['results'][0]['geometry']['location']['lat'];
		$lng = $curlResponse['results'][0]['geometry']['location']['lng'];
		$kordinates = $lat . ',' . $lng;

		ws_add_meta($postId, 'REAL_HOMES_property_location', $kordinates);

		//add post attr details
		if (!empty($postAttrDetails)) {
			$arrayAttrData = [];

			foreach ($postAttrDetails as $attrName => $value) {

				if ($attrName == 'ausstatt_kategorie') {
					continue;
				}

				$attrValGood = '';
				foreach ($value as $key1 => $value1) {
					
					if($key1 != '_val'){
						$attrValGood .= ucfirst(strtolower($key1)) . ' ';
					}
				}

				if ($attrValGood) {
					$attrName = ucfirst(strtolower($attrName));
					$attrValGood = trim($attrValGood);
					$arrayAttrData[$attrName] = $attrValGood;
				}
			}

			ws_add_meta($postId, 'REAL_HOMES_additional_details', $arrayAttrData);
		}

		//add agent and agency
		if (!empty($postAttrAgent) && !empty(trim($postAttrAgent['name']))) {

			$postAttrAgent['name'] = str_replace('||', '', $postAttrAgent['name']);
			$postAttrAgent['name'] = str_replace('II', '', $postAttrAgent['name']);
			$postAttrAgent['name'] = str_replace('  ', ' ', $postAttrAgent['name']);
			
			$checkExistAgent = get_page_by_title($postAttrAgent['name'], 'OBJECT', array('agent'));
			
			if (empty($checkExistAgent)) {

				// basic agent data
				$wpAgentData = array(
					'post_type' => 'agent',
					'post_title' => $postAttrAgent['name'],
					'post_content' => '',
					'post_status' => 'publish',
					'ping_status' => 'closed',
					'comment_status' => 'closed',
					'post_author' => 1,
				);

				//insert agent
				$agentId = createPost($wpAgentData);   // op_tools;

				//insert agent image
				my_sideload_image_new($agentId, $postAttrAgent['photo'], $ftpTemp, $postAttrAgent['name']);
				
				//add agent attr
				ws_add_meta($agentId, 'REAL_HOMES_agent_email', $postAttrAgent['email_direkt']);
				ws_add_meta($agentId, 'REAL_HOMES_mobile_number', $postAttrAgent['tel_handy']);
				ws_add_meta($agentId, 'REAL_HOMES_office_number', $postAttrAgent['tel_durchw']);
				ws_add_meta($agentId, 'REAL_HOMES_fax_number', $postAttrAgent['tel_fax']);
				
				//connect post and agent
				ws_add_meta($postId, 'REAL_HOMES_agents', $agentId);
			} else {
				//connect post and agent
				$agentId = $checkExistAgent->ID;
				ws_add_meta($postId, 'REAL_HOMES_agents', $agentId);
			}

			//add agency
			if (!empty(trim($postAttrAgent['firma']))) {
				$checkExistAgency = get_page_by_title($postAttrAgent['firma'], 'OBJECT', array('agency'));
				
				if (empty($checkExistAgency)) {
					// basic agency data
					$wpAgencyData = array(
						'post_type' => 'agency',
						'post_title' => $postAttrAgent['firma'],
						'post_content' => '',
						'post_status' => 'publish',
						'ping_status' => 'closed',
						'comment_status' => 'closed',
						'post_author' => 1,
					);

					//insert agency
					$agencyId = createPost($wpAgencyData);   // op_tools;
					
					//add agency attr
					ws_add_meta($agencyId, 'REAL_HOMES_agency_email', $postAttrAgent['email_centr']);
					ws_add_meta($agencyId, 'REAL_HOMES_mobile_number', $postAttrAgent['tel_handy']);
					ws_add_meta($agencyId, 'REAL_HOMES_office_number', $postAttrAgent['tel_durchw']);
					ws_add_meta($agencyId, 'REAL_HOMES_fax_number', $postAttrAgent['tel_fax']);
					ws_add_meta($agencyId, 'REAL_HOMES_facebook_url', $postAttrAgent['url']);
					
					//connect agent and agency
					ws_add_meta($agentId, 'REAL_HOMES_agency', $agencyId);
				} else {
					//connect agent and agency
					$agencyId = $checkExistAgency->ID;
					ws_add_meta($agentId, 'REAL_HOMES_agency', $agencyId);
				}
			}
		}
	}//for immo

	//remove unzip files
	array_map('unlink', array_filter((array) glob($ftpTemp . "*")));

	//work with zip file
	// $zipFileName = basename($origFolderFrom);
	$zipFileName = ($fileInfo['type'] == 'zip') ? $fileInfo['file'] : '';
	
	if(!empty($zipFileName)){

		copy($origFolderFrom . $zipFileName, $oldImportedZip . $zipFileName);
	
		//remove zip file
		unlink($origFolderFrom . $zipFileName);
	}

}//funktion

?>
<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2016 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Administration - File inspector
 * 
 */
ob_implicit_flush(true);
ob_end_flush();

require_once('../class2.php');

e107::coreLan('fileinspector', true);

if(!getperms('Y'))
{
	e107::redirect('admin');
	exit;
}

set_time_limit(18000);
$e_sub_cat = 'fileinspector';


if(isset($_GET['scan']))
{
	session_write_close();
	while (@ob_end_clean());

	//header("Content-type: text/html; charset=".CHARSET, true);
	//$css_file = file_exists(e_THEME.$pref['admintheme'].'/'.$pref['admincss']) ? e_THEME.$pref['admintheme'].'/'.$pref['admincss'] : e_THEME.$pref['admintheme'].'/'.$pref['admincss'];
	//	$fi = new file_inspector;

    /** @var file_inspector $fi */
	$fi = e107::getSingleton('file_inspector');

	echo "<!DOCTYPE html>
	<html> 
	<head>  	
		<title>Results</title>
		<script type='text/javascript' src='https://cdn.jsdelivr.net/jquery/2.2.1/jquery.min.js'></script>
		<link  rel='stylesheet' media='all' property='stylesheet' type='text/css' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css' />
	 
		".$fi->headerCss()." ".headerjs()."
		<body style='height:100%;background-color:#2F2F2F'>\n";

		//	define('e_IFRAME', true);
		//	require_once(e_ADMIN."auth.php");

		// echo "<br />loading..";

		// echo "..";
		//flush();

		$_POST = $_GET;

		if(vartrue($_GET['exploit']))
		{
			$fi->exploit();
		}
		else
		{
			$fi->scan_results();
		}

		//	require_once(e_ADMIN."footer.php");

	echo "</body>
	</html>";

	exit();

}
else
{
	// $fi = new file_inspector;
	$fi = e107::getSingleton('file_inspector');

	require_once(e_ADMIN.'auth.php');


	//	if(e_QUERY) {
	// $fi -> snapshot_interface();
	//}

	if(varset($_POST['scan']))
	{
		 $fi->exploit_interface();
		 $fi->scan_config();
	}
	elseif($_GET['mode'] == 'run')
	{
		$mes = e107::getMessage();
		$mes->addInfo(FR_LAN_32);//Run a Scan first
		echo $mes->render();
	}
	else
	{
		$fi->scan_config();
	}
}



class file_inspector {
    /** @var e_file_inspector */
	private $coreImage;
	private $coreImageVersion;

	private $root_dir;
	private $files = array();
    private $fileSizes = array();
	private $count = array();
	/** @deprecated What's this? */
	var $results = 0;
	private $totalFiles = 0;
	private $progress_units = 0;
	private $langs = array();
	private $lang_short = array();
	private $iconTag = array();

	private $options = array(
		'core'          => '',
		'type'          => 'tree',
		'missing'       => 0,
		'noncore'       => 9,
		'oldcore'       => 0,
		'integrity'     => 1,
		'regex'         => 0,
		'mod'           => '',
		'num'           => 0,
		'line'          => 0
	);
    /**
     * @var array
     */
    private $glyph;

    function setOptions($post)
	{
		foreach($this->options as $k=>$v)
		{
			if(isset($post[$k]))
			{
				$this->options[$k] = $post[$k];
			}
		}
	}

	function __construct()
	{
		$lng    = e107::getLanguage();
		$langs  = $lng->installed();

		if(isset($_GET['scan']))
		{
			$this->setOptions($_GET);
		}

		$lang_short = array();

		foreach($langs as $k=>$val)
		{
		    if($val == "English") // Core release language, so ignore it.
		    {
				unset($langs[$k]);
				continue;
			}

			$lang_short[] = $lng->convert($val);
		}

		$this->langs = $langs;
		$this->lang_short = $lang_short;

		$this->glyph = array(
			'folder_close'      => array('<i class="fa fa-times-circle-o"></i>'),
			'folder_up'         => array('<i class="fa fa-folder-open-o"></i>'),
			'folder_root'       => array('<i class="fa fa-folder-o"></i>'),

			'warning'           => array('<i class="fa fa-exclamation-triangle text-warning" ></i>'),
			'info'              => array('<i class="fa fa-info-circle text-primary" ></i>'),
			'fileinspector'     => array('<i class="fa fa-folder text-success" style="color:#F6EDB0;"></i>'),

			'folder'            => array('<i class="fa fa-folder text-success" style="color:#F6EDB0;"></i>'),
			'folder_check'      => array('<i class="fa fa-folder text-success" style="color:#F6EDB0" ></i>', FC_LAN_24 ),
			'folder_fail'       => array('<i class="fa fa-folder text-danger" ></i>', FC_LAN_25 ),
            'folder_uncalc'     => array('<i class="fa fa-folder-o" ></i>', FC_LAN_24 ),
			'folder_missing'    => array('<i class="fa fa-folder-o text-danger" ></i>', FC_LAN_26 ),
			'folder_warning'    => array('<i class="fa fa-folder text-warning" ></i>'),
			'folder_old'        => array('<i class="fa fa-folder-o text-warning" ></i>', FC_LAN_27 ),
			'folder_old_dir'    => array('<i class="fa fa-folder-o text-warning" ></i>'),
			'folder_unknown'    => array('<i class="fa fa-folder-o text-primary" ></i>', FC_LAN_28 ),

			'file_check'        => array('<i class="fa fa-file text-success" style="color:#F6EDB0" ></i>', FC_LAN_29),
			'file_core'        	=> array('<i class="fa fa-file-o text-success" style="color:#F6EDB0" ></i>', FC_LAN_30),
			'file_fail'         => array('<i class="fa fa-file text-danger" ></i>', FC_LAN_31 ),
			'file_missing'      => array('<i class="fa fa-file-o text-danger" ></i>', FC_LAN_32 ),
			'file_old'          => array('<i class="fa fa-file-o text-warning" ></i>', FC_LAN_33 ),
			'file_uncalc'       => array('<i class="fa fa-file-o " ></i>', FC_LAN_34 ),
			'file_warning'      => array('<i class="fa fa-file text-warning" ></i>', FC_LAN_35 ),
			'file_unknown'      => array('<i class="fa fa-file-o text-primary" ></i>', FC_LAN_36 ),
		);

		foreach($this->glyph as $k=>$v)
		{
			$this->iconTag[$k] = $this->glyph[$k][0];
		}

		$e107 = e107::getInstance();
		$this->coreImage = e107::getFileInspector('core');
		$this->coreImageVersion = $this->coreImage->getCurrentVersion();

		$this->countFiles();

		$this->root_dir = $e107 -> file_path;

		if(substr($this->root_dir, -1) == '/')
		{
			$this->root_dir = substr($this->root_dir, 0, -1);
		}

		if(isset($_POST['core']) && $_POST['core'] == 'integrity_fail_only')
		{
			$_POST['integrity'] = TRUE;
		}

		if(MAGIC_QUOTES_GPC && vartrue($_POST['regex']))
		{
			$_POST['regex'] = stripslashes($_POST['regex']);
		}

		if(!empty($_POST['regex']))
		{
			if($_POST['core'] == 'fail')
			{
				$_POST['core'] = 'all';
			}

			$_POST['missing'] = 0;
			$_POST['integrity'] = 0;
		}
	}


	private function opt($key)
	{
		return $this->options[$key];
	}


	// Find the Total number of core files before scanning begins.
	private function countFiles()
	{
	    return $this->totalFiles = iterator_count($this->coreImage->getPathIterator($this->coreImageVersion));
	}


    function getLegend()
	{
		return $this->glyph;
	}


	function renderHelp()
	{
		$text = "<table>";

		foreach($this->iconTag as $k=>$v)
		{
			$text .=  "<tr><td>".$v."</td><td>".$k."</td></tr>";

		}
		$text .= "</table>";
		// echo $text;
	}


	function scan_config()
	{
		$frm 	= e107::getForm();
		$ns 	= e107::getRender();
		$pref 	= e107::pref('core');

		if($_GET['mode'] == 'run')
		{
			return;
		}

		$tab = array();

		$head = "<div>
		<form action='".e_SELF."?mode=run' method='post' id='scanform'>";

		$text = "
		<table class='table  adminform'>";

	/*	$text .= "
		<tr>
		<td class='fcaption' colspan='2'>".LAN_OPTIONS."</td>
		</tr>";*/

		$coreOpts = array('integrity_fail_only'=>FC_LAN_6, 'all'=>LAN_ALL, 'none'=> LAN_NONE);

		$text .= "<tr>
		<td style='width: 35%'>
		".LAN_SHOW." ".FC_LAN_5.":
		</td>
		<td colspan='2' style='width: 65%'>".$frm->select('core',$coreOpts,$_POST['core'])."	</td>
		</tr>";


		$dispOpt = array('tree'=>FC_LAN_15, 'list'=>LAN_LIST);
		$text .= "<tr>
		<td style='width: 35%'>
		".FC_LAN_14.":
		</td>
		<td colspan='2' style='width: 65%'>".$frm->select('type', $dispOpt, $_POST['type'])."	</td>
		</td>
		</tr>";


		$text .= "<tr>
		<td style='width: 35%'>
		".LAN_SHOW." ".FC_LAN_13.":
		</td>
		<td colspan='2' style='width: 65%'>
		<input type='radio' name='missing' value='1'".(($_POST['missing'] == '1' || !isset($_POST['missing'])) ? " checked='checked'" : "")." /> ".LAN_YES."&nbsp;&nbsp;
		<input type='radio' name='missing' value='0'".($_POST['missing'] == '0' ? " checked='checked'" : "")." /> ".LAN_NO."&nbsp;&nbsp;
		</td>
		</tr>";

		$text .= "<tr>
		<td style='width: 35%'>
		".LAN_SHOW." ".FC_LAN_7.":
		</td>
		<td colspan='2' style='width: 65%'>
		<input type='radio' name='noncore' value='1'".(($_POST['noncore'] == '1' || !isset($_POST['noncore'])) ? " checked='checked'" : "")." /> ".LAN_YES."&nbsp;&nbsp;
		<input type='radio' name='noncore' value='0'".($_POST['noncore'] == '0' ? " checked='checked'" : "")." /> ".LAN_NO."&nbsp;&nbsp;
		</td>
		</tr>";

		$text .= "<tr>
		<td style='width: 35%'>
		".LAN_SHOW." ".FC_LAN_21.":
		</td>
		<td colspan='2' style='width: 65%'>
		<input type='radio' name='oldcore' value='1'".(($_POST['oldcore'] == '1' || !isset($_POST['oldcore'])) ? " checked='checked'" : "")." /> ".LAN_YES."&nbsp;&nbsp;
		<input type='radio' name='oldcore' value='0'".($_POST['oldcore'] == '0' ? " checked='checked'" : "")." /> ".LAN_NO."&nbsp;&nbsp;
		</td>
		</tr>";

		/*
		$text .= "<tr>
		<td style='width: 35%'>
		".FC_LAN_8.":
		</td>
		<td style='width: 65%; vertical-align: top'>
		<input type='radio' name='integrity' value='1'".(($_POST['integrity'] == '1' || !isset($_POST['integrity'])) ? " checked='checked'" : "")." /> ".LAN_YES."&nbsp;&nbsp;
		<input type='radio' name='integrity' value='0'".($_POST['integrity'] == '0' ? " checked='checked'" : "")." /> ".LAN_NO."&nbsp;&nbsp;
		</td></tr>";
		*/

		$text .= "</table>";

		$tab['basic'] = array('caption'=>LAN_OPTIONS, 'text'=>$text);

		if($pref['developer']) {

			$text2 = "<table class='table adminlist'>";
		/*	$text2 .= "<tr>
			<td class='fcaption' colspan='2'>".FC_LAN_17."</td>
			</tr>";*/

			$text2 .= "<tr>
			<td style='width: 35%'>
			".FC_LAN_18.":
			</td>
			<td colspan='2' style='width: 65%'>
			#<input class='tbox' type='text' name='regex' size='40' value='".htmlentities($_POST['regex'], ENT_QUOTES)."' />#<input class='tbox' type='text' name='mod' size='5' value='".$_POST['mod']."' />
			</td>
			</tr>";

			$text2 .= "<tr>
			<td style='width: 35%'>
			".FC_LAN_19.":
			</td>
			<td colspan='2' style='width: 65%'>
			<input type='checkbox' name='num' value='1'".(($_POST['num'] || !isset($_POST['num'])) ? " checked='checked'" : "")." />
			</td>
			</tr>";

			$text2 .= "<tr>
			<td style='width: 35%'>
			".FC_LAN_20.":
			</td>
			<td colspan='2' style='width: 65%'>
			<input type='checkbox' name='line' value='1'".(($_POST['line'] || !isset($_POST['line'])) ? " checked='checked'" : "")." />
			</td>
			</tr>";

			$text2 .= "
			</table>";

			$tab['advanced'] = array('caption'=>FC_LAN_17, 'text'=>$text2);
		}

		$tabText = e107::getForm()->tabs($tab);


		$foot = "
		<div class='buttons-bar center'>
		".$frm->admin_button('scan', LAN_GO, 'other')."
		</div>
		</form>
		</div>";

		$text = $head.$tabText.$foot;

		$ns->tablerender(FC_LAN_1, $text);

	}

    /**
     * @param $baseDir string Absolute path to the directory to inspect
     */
	protected function inspect($baseDir)
    {
        $this->inspect_existing($baseDir);
        $this->inspect_missing(array_keys($this->files));
    }

    private function inspect_existing($baseDir)
    {
        $absoluteBase = realpath($baseDir);
        if (!is_dir($absoluteBase)) return;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
        $index = 0;
        foreach ($iterator as $file)
        {
            $this->sendProgress($index++, $this->totalFiles);
            if ($file->isDir()) continue;

            $absolutePath = $file->getRealPath();
            $relativePath = preg_replace("/^" . preg_quote($absoluteBase . "/", "/") . "/", "", $absolutePath);

            if (empty($relativePath) || $relativePath == $absolutePath) continue;

            $this->files[$relativePath] = $this->coreImage->validate($relativePath);
            $this->fileSizes[$relativePath] = filesize($absolutePath);
            $this->updateFileSizeCounter($absolutePath, $this->files[$relativePath]);
        }
    }

    private function inspect_missing($existingPaths)
    {
        $dbIterator = $this->coreImage->getPathIterator($this->coreImageVersion);
        $dbPaths = iterator_to_array($dbIterator);
        $dbPaths = array_map(function ($defaultPath)
        {
            return $this->coreImage->defaultPathToCustomPath($defaultPath);
        }, $dbPaths);
        $missingPaths = array_diff($dbPaths, $existingPaths);
        foreach ($missingPaths as $relativePath)
        {
            $this->files[$relativePath] = $this->coreImage->validate($relativePath);
        }
    }

    private function updateFileSizeCounter($absolutePath, $validationCode)
    {
        $status = $this->getStatusForValidationCode($validationCode);
        $category = $this->statusToLegacyCountCategory($status);
        $fileSize = filesize($absolutePath);
        $this->count[$category]['size'] += $fileSize;

        if ($validationCode & e_file_inspector::VALIDATED_PATH_VERSION &&
            $validationCode & e_file_inspector::VALIDATED_FILE_EXISTS)
            $this->count['core']['size'] += $fileSize;
    }

    private function statusToLegacyCountCategory($status)
    {
        $category = $status;
        switch ($status)
        {
            case 'check':
                $category = 'pass';
                break;
            case 'uncalc':
                $category = 'uncalculable';
                break;
            case 'old':
                $category = 'deprecated';
                break;
        }
        return $category;
    }

    /**
     * @return string HTML output of the validated directory structure
     */
    private function generateScanResultsHtml()
    {
        $nestedFiles = [];
        foreach ($this->files as $relativePath => $validation)
        {
            if ($this->displayAllowed($validation))
                self::array_set($nestedFiles, $relativePath, $validation);
        }
        return $this->generateDirectoryHtml([SITENAME => $nestedFiles]);
    }

    private function generateDirectoryHtml($tree, $level = 0, $parentPath = '')
    {
        $html = '';

        $this->sortAscDirectoriesFirst($tree);
        $hide = $level;
        foreach ($tree as $fileName => $validationCode)
        {
            $relativePath = ltrim("$parentPath/$fileName", '/');
            if ($level === 0) $relativePath = '';
            $rowId = str_replace(" ", "%20", $relativePath);
            list($icon, $title) = $this->getGlyphForValidationCode($validationCode);
            $oldVersion = $this->getOldVersionOfPath($relativePath, $validationCode);
            $html .= "<div class=\"d\" title=\"$title\" style=\"margin-left: " . ($level * 8) . "px\">";
            $html .= "<span onclick=\"ec('$rowId')\">";
            $html .= $this->getTreeActionImageForFile($tree, $fileName, $rowId, $hide);
            $html .= "</span>&nbsp;<span onclick=\"sh('f_$rowId')\">" .
                $icon.
                "&nbsp;$fileName</span>";
            if (is_array($validationCode))
            {
                $html .= "<div id=\"d_$rowId\" " . ($hide ? "style=\"display:none\"" : "") . ">";
                $html .= $this->generateDirectoryHtml($validationCode, $level + 1, $relativePath);
                $html .= "</div>";
            }
            else
            {
                $html .= '<span style="float:right">';
                $html .= isset($this->fileSizes[$relativePath]) ? $this->parsesize($this->fileSizes[$relativePath]) : '';
                $html .= $oldVersion ? " (v$oldVersion)" : "";
                $html .= '</span>';
            }
            $html .= "</div>";
        }

        return $html;
    }

    private function sortAscDirectoriesFirst(array &$tree)
    {
        return uksort($tree, function ($a, $b) use ($tree)
        {
            if (is_array($tree[$a]) && !is_array($tree[$b])) return -1;
            elseif (!is_array($tree[$a]) && is_array($tree[$b])) return 1;
            return $a > $b;
        });
    }

    private function getTreeActionImageForFile($tree, $fileName, $id, $hide = false)
    {
        if (!is_array($tree[$fileName]))
        {
            $actionImage = 'blank';
            $actionAlt = ' ';
        }
        elseif ($hide)
        {
            $actionImage = 'expand';
            $actionAlt = '+';
        }
        else
        {
            $actionImage = 'contract';
            $actionAlt = '-';
        }

        return "<img id='e_$id' class='e' src='".e_IMAGE."fileinspector/$actionImage.png' alt='$actionAlt' width='15' />";
    }

    private function getGlyphForValidationCode($validationCodeOrArray)
    {
        if (is_array($validationCodeOrArray)) return $this->getWorstGlyphForFolder($validationCodeOrArray);
        return $this->glyph['file_' . $this->getStatusForValidationCode($validationCodeOrArray)];
    }

    private function getStatusForValidationCode($validationCode)
    {
        $status = 'unknown';
        if ($validationCode & e_file_inspector::VALIDATED)
            $status = 'check';
        elseif (!($validationCode & e_file_inspector::VALIDATED_FILE_EXISTS))
            $status = 'missing';
        elseif (!($validationCode & e_file_inspector::VALIDATED_FILE_SECURITY))
            $status = 'warning';
        elseif (!($validationCode & e_file_inspector::VALIDATED_PATH_KNOWN))
            $status = 'unknown';
        elseif (!($validationCode & e_file_inspector::VALIDATED_PATH_VERSION))
            $status = 'old';
        elseif (!($validationCode & e_file_inspector::VALIDATED_HASH_CALCULABLE))
            $status = 'uncalc';
        elseif (!($validationCode & e_file_inspector::VALIDATED_HASH_CURRENT))
            if ($validationCode & e_file_inspector::VALIDATED_HASH_EXISTS)
                $status = 'old';
            else
                $status = 'fail';
        return $status;
    }

    private function getStatusRank($status)
    {
        $rank = PHP_INT_MIN;
        switch ($status)
        {
            case 'unknown':
                $rank = -2;
                break;
            case 'uncalc':
                $rank = -1;
                break;
            case 'check':
                $rank = 0;
                break;
            case 'missing':
                $rank = 1;
                break;
            case 'old':
                $rank = 2;
                break;
            case 'fail':
                $rank = 3;
                break;
            case 'warning':
                $rank = 4;
                break;
        }
        return $rank;
    }

    private function getWorstGlyphForFolder($treeFolder)
    {
        $worstStatus = 'unknown';
        $worstStatusRank = -PHP_INT_MAX;
        array_walk_recursive($treeFolder, function ($value) use (&$worstStatus, &$worstStatusRank)
        {
            $currentStatus = $this->getStatusForValidationCode($value);
            $currentStatusRank = $this->getStatusRank($currentStatus);
            if ($currentStatusRank > $worstStatusRank)
            {
                $worstStatusRank = $currentStatusRank;
                $worstStatus = $currentStatus;
            }
        });
        return $this->glyph['folder_' . $worstStatus];
    }

    private function displayAllowed($validationCode)
    {
        if ($this->opt('core') == 'integrity_fail_only' &&
            $validationCode & e_file_inspector::VALIDATED)
            return false;
        elseif ($this->opt('core') == 'none' &&
        $validationCode & e_file_inspector::VALIDATED_PATH_VERSION)
            return false;

        $status = $this->getStatusForValidationCode($validationCode);
        $return = true;
        switch ($status)
        {
            case 'missing':
                $return = $this->opt('missing');
                break;
            case 'unknown':
                $return = $this->opt('noncore');
                break;
            case 'old':
                $return = $this->opt('oldcore');
                break;
        }
        return $return;
    }

    /**
     * Set an array item to a given value using "slash" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * Based on Illuminate\Support\Arr::set()
     *
     * @param array $array
     * @param string|null $key
     * @param mixed $value
     * @return array
     * @copyright Copyright (c) Taylor Otwell
     * @license https://github.com/illuminate/support/blob/master/LICENSE.md MIT License
     */
    private static function array_set(&$array, $key, $value)
    {
        if (is_null($key))
        {
            return $array = $value;
        }

        $keys = explode('/', $key);

        while (count($keys) > 1)
        {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key]))
            {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }


    function scan_results()
	{
	    $this->count = [
	        'core' => [
	            'num' => 0,
                'size' => 0,
            ],
            'fail' => [
                'num' => 0,
                'size' => 0,
            ],
            'pass' => [
                'num' => 0,
                'size' => 0,
            ],
            'uncalculable' => [
                'num' => 0,
                'size' => 0,
            ],
            'missing' => [
                'num' => 0,
            ],
            'deprecated' => [
                'num' => 0,
                'size' => 0,
            ],
            'unknown' => [
                'num' => 0,
                'size' => 0,
            ],
            'warning' => [
                'num' => 0,
                'size' => 0,
            ]
        ];
		$this->inspect($this->root_dir);

        array_walk_recursive($this->files, function ($validationCode)
        {
            $status = $this->getStatusForValidationCode($validationCode);
            $category = $this->statusToLegacyCountCategory($status);
            $this->count[$category]['num']++;

            if ($validationCode & e_file_inspector::VALIDATED_PATH_VERSION &&
                $validationCode & e_file_inspector::VALIDATED_FILE_EXISTS)
                $this->count['core']['num']++;
        });

        $this->sendProgress($this->totalFiles, $this->totalFiles);

		echo "<div style='display:block;height:30px'>&nbsp;</div>";

		if($this->opt('type') == 'tree')
		{
			$text = "<div style='text-align:center'>
			<table class='table adminlist'>
			<tr>
			<th class='fcaption' colspan='2'>".FR_LAN_2."</th>
			</tr>";

			$text .= "<tr style='display: none'><td style='width:60%'></td><td style='width:40%'></td></tr>";

			$text .= "<tr>
			<td style='width:60%;padding:0; '>
			<div style=' min-height:400px; max-height:800px; overflow: auto; padding-bottom:50px'>
			".$this->generateScanResultsHtml()."
			</div>
			</td>
			<td style='width:40%; height:5000px; vertical-align: top; overflow:auto'><div>";
		}
		else
		{
			$text = "<div style='text-align:center'>
			<table class='table table-striped adminlist'>
			<tr>
			<th class='fcaption' colspan='2'>".FR_LAN_2."</th>
			</tr>";

			$text .= "<tr>
			<td colspan='2'>";
		}

		$text .= "<table class='table-striped table adminlist' id='initial'>";

		if($this->opt('type') == 'tree')
		{
			$text .= "<tr><th class='f' >".FR_LAN_3."</th>
			<th class='s' style='text-align: right; padding-right: 4px' onclick=\"sh('f_".dechex(crc32($this->root_dir))."')\">
			<b class='caret'></b></th></tr>";
		}
		else
		{
			$text .= "<tr><th class='f' colspan='2'>".FR_LAN_3."</th></tr>";
		}

		if($this->opt('core') != 'none')
		{
			$text .= "<tr><td class='f'>".$this->iconTag['file_core']."&nbsp;".FC_LAN_5.":&nbsp;".($this->count['core']['num'] ? $this->count['core']['num'] : LAN_NONE)."&nbsp;</td>
			<td class='s'>".$this->parsesize($this->count['core']['size'], 2)."</td></tr>";
		}
		if($this->opt('missing'))
		{
			$text .= "<tr><td class='f' colspan='2'>".$this->iconTag['file_missing']."&nbsp;".FC_LAN_13.":&nbsp;".($this->count['missing']['num'] ? $this->count['missing']['num'] : LAN_NONE)."&nbsp;</td></tr>";
		}
		if($this->opt('noncore'))
		{
			$text .= "<tr><td class='f'>".$this->iconTag['file_unknown']."&nbsp;".FC_LAN_7.":&nbsp;".($this->count['unknown']['num'] ? $this->count['unknown']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['unknown']['size'], 2)."</td></tr>";
		}
		if($this->opt('oldcore'))
		{
			$text .= "<tr><td class='f'>".$this->iconTag['file_old']."&nbsp;".FR_LAN_24.":&nbsp;".($this->count['deprecated']['num'] ? $this->count['deprecated']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['deprecated']['size'], 2)."</td></tr>";
		}
		if($this->opt('core') == 'all')
		{
			$text .= "<tr><td class='f'>".$this->iconTag['file']."&nbsp;".FR_LAN_6.":&nbsp;".($this->count['core']['num'] + $this->count['unknown']['num'] + $this->count['deprecated']['num'])."&nbsp;</td><td class='s'>".$this->parsesize($this->count['core']['size'] + $this->count['unknown']['size'] + $this->count['deprecated']['size'], 2)."</td></tr>";
		}

		if($this->count['warning']['num'])
		{
			$text .= "<tr><td colspan='2'>&nbsp;</td></tr>";
			$text .= "<tr><td style='padding-left: 4px' colspan='2'>
			".$this->iconTag['warning']."&nbsp;<b>".FR_LAN_26."</b></td></tr>";

			$text .= "<tr><td class='f'>".$this->iconTag['file_warning']." ".FR_LAN_28.": ".($this->count['warning']['num'] ? $this->count['warning']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['warning']['size'], 2)."</td></tr>";

			$text .= "<tr><td class='w' colspan='2'><div class='alert alert-warning'>".FR_LAN_27."</div></td></tr>";

		}
		if($this->opt('integrity') && ($this->opt('core') != 'none'))
		{
			$integrity_icon = $this->count['fail']['num'] ? 'integrity_fail.png' : 'integrity_pass.png';
			$integrity_text = $this->count['fail']['num'] ? '( '.$this->count['fail']['num'].' '.FR_LAN_19.' )' : '( '.FR_LAN_20.' )';
			$text .= "<tr><td colspan='2'>&nbsp;</td></tr>";
			$text .= "<tr><th class='f' colspan='2'>".FR_LAN_7." ".$integrity_text."</th></tr>";

			$text .= "<tr><td class='f'>".$this->iconTag['file_check']."&nbsp;".FR_LAN_8.":&nbsp;".($this->count['pass']['num'] ? $this->count['pass']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['pass']['size'], 2)."</td></tr>";
			$text .= "<tr><td class='f'>".$this->iconTag['file_fail']."&nbsp;".FR_LAN_9.":&nbsp;".($this->count['fail']['num'] ? $this->count['fail']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['fail']['size'], 2)."</td></tr>";
			$text .= "<tr><td class='f'>".$this->iconTag['file_uncalc']."&nbsp;".FR_LAN_25.":&nbsp;".($this->count['uncalculable']['num'] ? $this->count['uncalculable']['num'] : LAN_NONE)."&nbsp;</td><td class='s'>".$this->parsesize($this->count['uncalculable']['size'], 2)."</td></tr>";

			$text .= "<tr><td colspan='2'>&nbsp;</td></tr>";

			$text .= "<tr><td class='f' colspan='2'>".$this->iconTag['info']."&nbsp;".FR_LAN_10.":&nbsp;</td></tr>";

			$text .= "<tr><td style='padding-right: 4px' colspan='2'>
			<ul><li>
			<a href=\"#\" onclick=\"expandit('i_corrupt')\">".FR_LAN_11."...</a><div style='display: none' id='i_corrupt'>
			".FR_LAN_12."<br /><br /></div>
			</li><li>
			<a href=\"#\" onclick=\"expandit('i_date')\">".FR_LAN_13."...</a><div style='display: none' id='i_date'>
			".FR_LAN_14."<br /><br /></div>
			</li><li>
			<a href=\"#\" onclick=\"expandit('i_edit')\">".FR_LAN_15."...</a><div style='display: none' id='i_edit'>
			".FR_LAN_16."<br /><br /></div>
			</li><li>
			<a href=\"#\" onclick=\"expandit('i_cvs')\">".FR_LAN_17."...</a><div style='display: none' id='i_cvs'>
			".FR_LAN_18."<br /><br /></div>
			</li></ul>
			</td></tr>";
		}

		if($this->opt('type') == 'tree' && !$this->results && $this->opt('regex'))
		{
			$text .= "</td></tr>
			<tr><td style='padding-right: 4px; text-align: center' colspan='2'><br />".FR_LAN_23."</td></tr>";
		}

		$text .= "</table>";

		if($this->opt('type') != 'tree')
		{
			$text .= "<br /></td></tr><tr>
			<td colspan='2'>
			<table class='table table-striped'>";
			if(!$this->results && $this->opt('regex'))
			{
				$text .= "<tr><td class='f' style='padding-left: 4px; text-align: center' colspan='2'>".FR_LAN_23."</td></tr>";
			}

            ksort($this->files);
            foreach ($this->files as $relativePath => $validation)
            {
                if (!$this->displayAllowed($validation)) continue;

                list($icon, $title) = $this->getGlyphForValidationCode($validation);
                $text .= '<tr><td class="f" title="'.$title.'">';
                $text .= "$icon ";
                $text .= htmlspecialchars($relativePath);
                $text .= '</td><td class="s">';
                $text .= isset($this->fileSizes[$relativePath]) ? $this->parsesize($this->fileSizes[$relativePath]) : '';
                $oldVersion = $this->getOldVersionOfPath($relativePath, $validation);
                $text .= $oldVersion ? " (v$oldVersion)" : "";
                $text .= '</td>';
                $text .= '</tr>';
            }
        }

		if($this->opt('type') != 'tree') {
			$text .= "</td>
			</tr></table>";
		}

		$text .= "</td></tr>";

		$text .= "</table>
		</div><br />";

		echo e107::getMessage()->render();
		echo $text;


	 //$ns->tablerender(FR_LAN_1.'...', $text);

	}

    function checksum($filename)
	{
		$checksum = md5(str_replace(array(chr(13),chr(10)), "", file_get_contents($filename)));
		return $checksum;
	}

	function parsesize($size, $dec = 0) {
		$size = $size ? $size : 0;
		$kb = 1024;
		$mb = 1024 * $kb;
		$gb = 1024 * $mb;
		$tb = 1024 * $gb;
		if($size < $kb) {
			return $size." ".CORE_LAN_B;
		} elseif($size < $mb) {
			return round($size/$kb)." ".CORE_LAN_KB;
		} elseif($size < $gb) {
			return round($size/$mb, $dec)." ".CORE_LAN_MB;
		} elseif($size < $tb) {
			return round($size/$gb, $dec)." ".CORE_LAN_GB;
		} else {
			return round($size/$tb, $dec)." ".CORE_LAN_TB;
		}
	}

	function regex_match($file) {
		$file_content = file_get_contents($file);
		$match = preg_match($_POST['regex'], $file_content);

		return $match;
	}


	function sendProgress($rand,$total)
	{
		if($this->progress_units <40 && ($rand != $total))
		{
			$this->progress_units++;
			return;
		}
		else
		{
			$this->progress_units = 0;
		}

		$inc = round(($rand / $total) * 100);

		if($inc == 0)
		{
			return;
		}


		echo "<div  style='display:block;position:absolute;top:20px;width:100%;'>
		<div style='width:700px;position:relative;margin-left:auto;margin-right:auto;text-align:center'>";

		$active = "active";

		if($inc >= 100)
		{
			$inc = 100;
			$active = "";
		}

		echo e107::getForm()->progressBar('inspector',$inc);

		/*	echo '<div class="progress progress-striped '.$active.'">
    			<div class="bar" style="width: '.$inc.'%"></div>
   		 </div>';*/


		echo "</div>
		</div>";

		return;


		//	exit;
		/*
	    echo "<div style='margin-left:auto;margin-right:auto;border:2px inset black;height:20px;width:700px;overflow:hidden;text-align:left'>
		<img src='".THEME."images/bar.jpg' style='width:".$inc."%;height:20px;vertical-align:top' />
		</div>";
		*/
		/*

		echo "<div style='width:100%;background-color:#EEEEEE'>".$diz."</div>";


		if($total > 0)
		{
			echo "<div style='width:100%;background-color:#EEEEEE;text-align:center'>".$inc ."%</div>";
		}

		echo "</div>
		</div>";
		*/
	}


	function exploit_interface()
	{
		//	global $ns;
		$ns = e107::getRender();

		$query = http_build_query($_POST);

		$text = "

    	<iframe src='".e_SELF."?$query' width='96%' style='margin-left:0; width: 98%; height:100vh; min-height: 100000px; border: 0px' frameborder='0' scrolling='auto' ></iframe>

 		";
		 $ns->tablerender(FR_LAN_1, $text);
	}


	function headerCss()
	{
		$pref = e107::getPref();

		echo "<!-- *CSS* -->\n";
		$e_js =  e107::getJs();

		// Core CSS - XXX awaiting for path changes
		if(!isset($no_core_css) || !$no_core_css)
		{
			//echo "<link rel='stylesheet' href='".e_FILE_ABS."e107.css' type='text/css' />\n";
			$e_js->otherCSS('{e_WEB_CSS}e107.css');
		}


		if(!deftrue('e_IFRAME') && isset($pref['admincss']) && $pref['admincss'])
		{
			$css_file = file_exists(THEME.'admin_'.$pref['admincss']) ? 'admin_'.$pref['admincss'] : $pref['admincss'];
			//echo "<link rel='stylesheet' href='".$css_file."' type='text/css' />\n";
			$e_js->themeCSS($css_file);
		}
		elseif(isset($pref['themecss']) && $pref['themecss'])
		{
			$css_file = file_exists(THEME.'admin_'.$pref['themecss']) ? 'admin_'.$pref['themecss'] : $pref['themecss'];
			//echo "<link rel='stylesheet' href='".$css_file."' type='text/css' />\n";
			$e_js->themeCSS($css_file);
		}
		else
		{
			$css_file = file_exists(THEME.'admin_style.css') ? 'admin_style.css' : 'style.css';
			//echo "<link rel='stylesheet' href='".$css_file."' type='text/css' />\n";
			$e_js->themeCSS($css_file);
		}


		$e_js->renderJs('other_css', false, 'css', false);
		echo "\n<!-- footer_other_css -->\n";

		// Core CSS
		$e_js->renderJs('core_css', false, 'css', false);
		echo "\n<!-- footer_core_css -->\n";

		// Plugin CSS
		$e_js->renderJs('plugin_css', false, 'css', false);
		echo "\n<!-- footer_plugin_css -->\n";

		// Theme CSS
		//echo "<!-- Theme css -->\n";
		$e_js->renderJs('theme_css', false, 'css', false);
		echo "\n<!-- footer_theme_css -->\n";

		// Inline CSS - not sure if this should stay at all!
		$e_js->renderJs('inline_css', false, 'css', false);
		echo "\n<!-- footer_inline_css -->\n";

        $text = "
<style type='text/css'>
<!--\n";
        if (vartrue($_POST['regex']))
        {
            $text .= ".f { padding: 1px 0px 1px 8px; vertical-align: bottom; width: 90% }\n";
        }
        else
        {
            $text .= ".f { padding: 1px 0px 1px 8px; vertical-align: bottom; width: 90%; white-space: nowrap }\n";
        }
        $text .= ".d { margin: 2px 0px 1px 8px; cursor: default; white-space: nowrap }
.s { padding: 1px 8px 1px 0px; vertical-align: bottom; width: 10%; white-space: nowrap }
.t { margin-top: 1px; width: 100%; border-collapse: collapse; border-spacing: 0px }
.w { padding: 1px 0px 1px 8px; vertical-align: bottom; width: 90% }
.i { width: 16px; height: 16px }
.e { width: 9px; height: 9px }
i.fa-folder-open-o, i.fa-times-circle-o { cursor:pointer }
-->
</style>\n";
        echo $text;
	}

    /**
     * Get the PHP-standard version of the hash of the relative path
     *
     * @todo FIXME performance: This method checksums old files a second time.
     * @param string $relativePath Relative path to checksum
     * @param int $validationCode e_file_inspector validation bits
     * @return false|string
     */
    private function getOldVersionOfPath($relativePath, $validationCode)
    {
        $oldVersion = false;
        if (($validationCode & e_file_inspector::VALIDATED_HASH_EXISTS) &&
            !($validationCode & e_file_inspector::VALIDATED_HASH_CURRENT))
        {
            $dbChecksums = $this->coreImage->getChecksums($relativePath);
            $actualChecksum = $this->coreImage->checksumPath(e_BASE . $relativePath);
            $oldVersion = array_search($actualChecksum, $dbChecksums);
        }
        return $oldVersion;
    }

}

function fileinspector_adminmenu() //FIXME - has problems when navigation is on the LEFT instead of the right. 
{
	$var['setup']['text'] = FC_LAN_11;
	$var['setup']['link'] = e_SELF."?mode=setup";

	$var['run']['text'] = FR_LAN_2;
	$var['run']['link'] = e_SELF."?mode=run";

	$icon  = e107::getParser()->toIcon('e-fileinspector-24');
	$caption = $icon."<span>".FC_LAN_1."</span>";

	e107::getNav()->admin($caption, $_GET['mode'], $var);
}

function e_help()
{

	//	$fi = new file_inspector;
	$fi = e107::getSingleton('file_inspector');
	$list = $fi->getLegend();

	$text = '';
	foreach($list as $v)
	{
		if(!empty($v[1]))
		{
			$text .= "<div>".$v[0]." ".$v[1]."</div>";
		}

	}

	return array('caption'=>FC_LAN_37, 'text'=>$text);

}


require_once(e_ADMIN.'footer.php');

function headerjs()
{
e107::js('footer', '{e_WEB}/js/core/all.jquery.js', 'jquery', 1);
$text = e107::getJs()->renderJs('footer', 1, true, true);
$text .= "<script type='text/javascript'>
<!--
c = new Image(); c = '".SITEURLBASE.e_IMAGE_ABS."fileinspector/contract.png';
e = '".SITEURLBASE.e_IMAGE_ABS."fileinspector/expand.png';
function ec(ecid) {
	icon = document.getElementById('e_' + ecid).src;
	if(icon.indexOf('expand.png') !== -1) {
		document.getElementById('e_' + ecid).src = c;
	} else {
		document.getElementById('e_' + ecid).src = e;
	}
	div = document.getElementById('d_' + ecid).style;
	if(div.display == 'none') {
		div.display = '';
	} else {
		div.display = 'none';
	}
}
var hideid = 'initial';
function sh(showid) {
	if(hideid != showid) {
		show = document.getElementById(showid).style;
		hide = document.getElementById(hideid).style;
		show.display = '';
		hide.display = 'none';
		hideid = showid;
	}
}
//-->
</script>";

return $text;
}

?>
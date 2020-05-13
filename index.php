<?php
$start = microtime(TRUE);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$autoloader = require 'vendor/autoload.php';
require_once('jpgraph/jpgraph.php');
define('NEW_LANGUAGE_CYRILLIC', true);
use const NEW_LANGUAGE_CYRILLIC as LANGUAGE_CYRILLIC;
require_once('jpgraph/jpgraph_bar.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DrawChart extends Thread {
	public $identifier;
	public $iteration;
	public $single_stat;
	public $length_needed;
	public $loader;

	public function __construct($autoloader, string $identifier, int $iteration, object $single_stat, int $length_needed) {
		$this->identifier = $identifier;
		$this->iteration = $iteration;
		$this->single_stat = $single_stat;
		$this->length_needed = $length_needed;
		$this->loader = $autoloader;
	}

	public function run(){
		$this->loader->register();
		draw_chart($this->identifier, $this->iteration, $this->single_stat, $this->length_needed);
	}
}

$ps = isset($argv[1]) && intval($argv[1]) == $argv[1] ? $argv[1] : 1;
$gb_ram = isset($argv[2]) && intval($argv[2]) == $argv[2] ? $argv[2] : 16;

if($gb_ram < 16) 
	echo 'RECOMENDED MINIMUM 16GB RAM, if you had\'t enought try to add cache https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/' . PHP_EOL;

ini_set('memory_limit', $gb_ram  . 'G');
echo 'RUNNED ON ' . $ps . ' PROCESSORS WITH ' . $gb_ram . 'GB RAM LIMIT' . PHP_EOL;

foreach (glob('json/*.json') as $filename) {
	$exp_name = str_replace(['json/', '.json'], '', $filename);
	if (!file_exists('xlsx/' . $exp_name . '_2.xlsx')) {
		magic($filename);
	} else {
		echo $exp_name . ' IS DONE!' . PHP_EOL;
	}
}

$end = microtime(TRUE);
echo "The code took " . ($end - $start) . " seconds to complete.";

function magic(String $filename) {
	global $ps, $autoloader;

	$p = new Pool($ps);
	
	$file_content = file_get_contents($filename);
	$json = json_decode($file_content);
	
	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	
	$length_needed = 0;
	foreach($json->stats as $stat) {
		$current_max = max(array_keys((array) $stat->hammingDistancePairs->pairedDistancesEnumerated));
		if ($current_max > $length_needed) 
			$length_needed = $current_max;
		
		$current_max = max(array_keys((array) $stat->hammingDistanceWild->distancesEnumerated));
		if ($current_max > $length_needed) 
			$length_needed = $current_max;
		
		$current_max = max(array_keys((array) $stat->hammingDistanceTarget->distancesEnumerated));
		if ($current_max > $length_needed) 
			$length_needed = $current_max;
	}
	
	basic_magic($sheet, $length_needed);
	$offset = 0;
	
	if (!file_exists('xlsx/' . $json->identifier . '_charts/'))
		mkdir('xlsx/' . $json->identifier . '_charts/');
	
	$second = 0;
	foreach($json->stats as $iteration => $single_stat) {
		$task = new DrawChart($autoloader, $json->identifier, $iteration + 1, $single_stat, $length_needed);
		if ($ps > 1)
			$p->submit($task);
		else
			$task->run();
		if ($iteration == 10000) {
			$writer = new Xlsx($spreadsheet);
			$writer->save('xlsx/' . $json->identifier . '_1.xlsx');
	
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			basic_magic($sheet, $length_needed);
			$offset = 10000;
			$second = 1;
			while ($p->collect()) continue;
			$p->shutdown();
			$p = new Pool($ps);
			echo 'SPLITTED' . PHP_EOL;	
		}
		advanced_magic($sheet, $iteration - $offset, $single_stat, $length_needed, $second);
		echo $json->identifier . ' - ' . ($iteration + 1) . PHP_EOL;
	}
	$writer = new Xlsx($spreadsheet);
	$writer->save('xlsx/' . $json->identifier . '_2.xlsx');	
	while ($p->collect()) continue;
	$p->shutdown();	
	echo 'SAVED' . PHP_EOL;
}

function draw_chart(string $identifier, int $iteration, stdClass $single_stat, int $length_needed) {
	$width = 2000;
	$height = 400;
	$margin = 40;
	
	$hdpmax = array_sum(array_values((array) $single_stat->hammingDistancePairs->pairedDistancesEnumerated));
	$hdwmax = array_sum(array_values((array) $single_stat->hammingDistanceWild->distancesEnumerated));
	$hdtmax = array_sum(array_values((array) $single_stat->hammingDistanceTarget->distancesEnumerated));
	
	$data1y = array();
	$data2y = array();
	$data3y = array();
		
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$data1y[$length_i] = isset($single_stat->hammingDistancePairs->pairedDistancesEnumerated->$length_i) ? $single_stat->hammingDistancePairs->pairedDistancesEnumerated->$length_i  / $hdpmax : 0;
		$data2y[$length_i] = isset($single_stat->hammingDistanceWild->distancesEnumerated->$length_i) ? $single_stat->hammingDistanceWild->distancesEnumerated->$length_i / $hdwmax : 0;
		$data3y[$length_i] = isset($single_stat->hammingDistanceTarget->distancesEnumerated->$length_i) ? $single_stat->hammingDistanceTarget->distancesEnumerated->$length_i / $hdtmax : 0;
	}
		
	$txt =
		$identifier . PHP_EOL . 
		'Ітерація #' . $iteration . PHP_EOL . PHP_EOL . 
		'Поліморфних генів у популяції з 1 заміною:' . PHP_EOL . $single_stat->singlePolymorphicGenesPercentageAccornigToTarget . PHP_EOL . PHP_EOL . 
		'Поліморфних генів у популяції з кількома замінами:' . PHP_EOL . $single_stat->multiplePolymorphicGenesPercentageAccornigToTarget . PHP_EOL . PHP_EOL . 
		'Кількість особин в популяції:' . PHP_EOL . $hdwmax . PHP_EOL . PHP_EOL;
	$txt2 = 'Математичне сподівання:' . PHP_EOL . $single_stat->hammingDistancePairs->stats->mathExpectation . PHP_EOL . PHP_EOL . 
		'Cереднє квадратичне відхилення:' . PHP_EOL . $single_stat->hammingDistancePairs->stats->sigma . PHP_EOL . PHP_EOL . 
		'Розмах:' . PHP_EOL . 
		$single_stat->hammingDistancePairs->stats->minValue
		. ', ' . 
		$single_stat->hammingDistancePairs->stats->maxValue
		. PHP_EOL . PHP_EOL . 
		'Коефіцієнт варіації:' . PHP_EOL . $single_stat->hammingDistancePairs->stats->variationFactor . PHP_EOL . PHP_EOL
	;

	$graph = new Graph($width, $height);    
	$graph->SetScale("textlin", 0.0, 1.0);
	$graph->yscale->SetAutoTicks();

	$graph->SetShadow();
	$graph->img->SetMargin($margin, 0, 10, $margin);

	$b1plot = new BarPlot($data1y);
		
	$graph->Add($b1plot);
	$b1plot->SetColor("blue");
	$b1plot->SetFillColor("blue");

	$graph->xaxis->SetLabelFormatCallback('number_format'); 

	$handle1 = $graph->Stroke( _IMG_HANDLER);
	$graph2 = new Graph($width, $height);
	$graph2->SetScale("textlin", 0.0, 1.0);
	$graph2->yscale->SetAutoTicks();
	$graph2->xaxis->SetLabelFormatCallback('number_format');

	$graph2->SetShadow();
	$graph2->img->SetMargin($margin, 0, 10, $margin);
	
	$b2plot = new BarPlot($data2y);
		
	$graph2->Add($b2plot);
	$b2plot->SetColor("green");
	$b2plot->SetFillColor("green");
	
	$handle2 =  $graph2->Stroke( _IMG_HANDLER);
	 
	$graph3 = new Graph($width, $height);
	$graph3->SetScale("textlin", 0.0, 1.0);
	$graph3->yscale->SetAutoTicks();
	$graph3->xaxis->SetLabelFormatCallback('number_format');

	$graph3->SetShadow();
	$graph3->img->SetMargin($margin, 0, 10, $margin);

	$b3plot = new BarPlot($data3y);
		
	$graph3->Add($b3plot);
	$b3plot->SetColor("red");
	$b3plot->SetFillColor("red");
	
	$handle3 =  $graph3->Stroke( _IMG_HANDLER);
	 
	$image = imagecreate($width, $height * 4);
	$bg = imagecolorallocate($image, 255, 255, 255);
	$textcolor = imagecolorallocate($image, 0, 0, 0);
	$font = "arial.ttf"; 
	imagettftext($image, 14, 0, $margin, $margin * 2.5, $textcolor, $font, $txt);
	imagettftext($image, 14, 0, ($width - ($margin * 2)) / 2, $margin * 2.5, $textcolor, $font, $txt2);
	imagecopy($image, $handle1, 0, $height, 0, 0, $width, $height);
	imagecopy($image, $handle2, 0, $height * 2, 0, 0, $width, $height);
	imagecopy($image, $handle3, 0, $height * 3, 0, 0, $width, $height);
	imagepng($image, 'xlsx/' . $identifier . '_charts/' . $iteration . '.png');
}

function basic_magic(Worksheet $sheet, int $length_needed) {
	$sheet->setCellValue('A1', 'Номер ітерації');
	$sheet->setCellValue('A2', 'Кількість особин у популяції');
	$sheet->setCellValue('A3', '«Дикий тип»: % поліморфних генів');
	$sheet->setCellValue('A4', '«Дикий тип»: кількість поліморфних генів');
	$sheet->setCellValue('A5', 'Модуль різниці середнього здоров’я в популяції від оптимального');
	$sheet->setCellValue('A6', 'Модуль різниці найкращого здоров’я в популяції від оптимального');
	$sheet->setCellValue('A7', 'Відсоток поліморфних нейтральних генів у популяції з 1 заміною');
	$sheet->setCellValue('A8', 'Відсоток поліморфних нейтральних генів у популяції з кількома замінами');
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$sheet->setCellValue('A' . (9 + $length_i), 'Попарна відстань ' . $length_i);
	}
	$sheet->setCellValue('A' . (10 + $length_needed), 'Відстань: математичне сподівання');
	$sheet->setCellValue('A' . (11 + $length_needed), 'Відстань: середнє квадратичне відхилення');
	$sheet->setCellValue('A' . (12 + $length_needed), 'Відстань: мода');
	$sheet->setCellValue('A' . (13 + $length_needed), 'Коефіцієнт варіації');
	$sheet->setCellValue('A' . (14 + $length_needed), 'Мінімальне, максимальне значення відстані');
	
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$sheet->getCellByColumnAndRow(1, 15 + $length_needed + $length_i)->setValue('Відстань ' .	 $length_i . ' до цільового ланцюжка');
	}
	$sheet->setCellValue('A' . (16 + $length_needed * 2), 'Відстань: математичне сподівання');
	$sheet->setCellValue('A' . (17 + $length_needed * 2), 'Відстань: середнє квадратичне відхилення');
	$sheet->setCellValue('A' . (18 + $length_needed * 2), 'Відстань: мода');
	$sheet->setCellValue('A' . (19 + $length_needed * 2), 'Коефіцієнт варіації');
	$sheet->setCellValue('A' . (20 + $length_needed * 2), 'Мінімальне, максимальне значення відстані');
	
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$sheet->getCellByColumnAndRow(1, 21 + $length_needed * 2 + $length_i)->setValue('Відстань ' .	 $length_i . ' до дикого типу');
	}
	$sheet->setCellValue('A' . (22 + $length_needed * 3), 'Відстань: математичне сподівання');
	$sheet->setCellValue('A' . (23 + $length_needed * 3), 'Відстань: середнє квадратичне відхилення');
	$sheet->setCellValue('A' . (24 + $length_needed * 3), 'Відстань: мода');
	$sheet->setCellValue('A' . (25 + $length_needed * 3), 'Коефіцієнт варіації');
	$sheet->setCellValue('A' . (26 + $length_needed * 3), 'Мінімальне, максимальне значення відстані');

	$sheet->getStyle('A1:A' . (26 + $length_needed * 3))->getFont()->setBold(true);
	$sheet->getColumnDimension('A')->setAutoSize(true);
	
	$sheet->freezePane('B2');
}

function advanced_magic(Worksheet $sheet, int $iteration, stdClass $stat, int $length_needed, $second = 0) {
	$column = 2 + $iteration;
	
	$hdpmax = array_sum(array_values((array) $stat->hammingDistancePairs->pairedDistancesEnumerated));
	$hdwmax = array_sum(array_values((array) $stat->hammingDistanceWild->distancesEnumerated));
	$hdtmax = array_sum(array_values((array) $stat->hammingDistanceTarget->distancesEnumerated));
	
	$sheet->getStyle(Coordinate::stringFromColumnIndex($column))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
		
	$sheet->getCellByColumnAndRow($column, 1)->setValue($iteration + 1 + $second * 10000);
	$sheet->getCellByColumnAndRow($column, 3)->setValue($stat->wildTypeInfo->polymorphicGenesToTargetPercentage);
	$sheet->getCellByColumnAndRow($column, 4)->setValue($stat->wildTypeInfo->polymorphicGenesToTargetCount);
	$sheet->getCellByColumnAndRow($column, 5)->setValue($stat->averageHealthDeviation);
	$sheet->getCellByColumnAndRow($column, 6)->setValue($stat->maxHealthDeviation);	
	$sheet->getCellByColumnAndRow($column, 7)->setValue($stat->singlePolymorphicGenesPercentageAccornigToTarget);
	$sheet->getCellByColumnAndRow($column, 8)->setValue($stat->multiplePolymorphicGenesPercentageAccornigToTarget);
	$population = 0;
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$this_count = isset($stat->hammingDistancePairs->pairedDistancesEnumerated->$length_i) ? $stat->hammingDistancePairs->pairedDistancesEnumerated->$length_i : 0;
		$sheet->getCellByColumnAndRow($column, 9 + $length_i)->setValue($this_count / $hdpmax);
		$population += isset($stat->hammingDistanceTarget->distancesEnumerated->$length_i) ? $stat->hammingDistanceTarget->distancesEnumerated->$length_i : 0;
	}
	$sheet->getCellByColumnAndRow($column, 2)->setValue($population);
	$sheet->getCellByColumnAndRow($column, 10 + $length_needed)->setValue($stat->hammingDistancePairs->stats->mathExpectation);
	$sheet->getCellByColumnAndRow($column, 11 + $length_needed)->setValue($stat->hammingDistancePairs->stats->sigma);
	$sheet->getCellByColumnAndRow($column, 12 + $length_needed)->setValue(join(', ', $stat->hammingDistancePairs->stats->moda));
	$sheet->getCellByColumnAndRow($column, 13 + $length_needed)->setValue($stat->hammingDistancePairs->stats->variationFactor);
	$sheet->getCellByColumnAndRow($column, 14 + $length_needed)->setValue($stat->hammingDistancePairs->stats->minValue . ', ' . $stat->hammingDistancePairs->stats->maxValue);
	
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$sheet->getCellByColumnAndRow($column, 15 + $length_needed + $length_i)->setValue(isset($stat->hammingDistanceTarget->distancesEnumerated->$length_i) ? $stat->hammingDistanceTarget->distancesEnumerated->$length_i / $hdtmax : 0);
	}
	$sheet->getCellByColumnAndRow($column, 16 + $length_needed * 2)->setValue($stat->hammingDistanceTarget->stats->mathExpectation);
	$sheet->getCellByColumnAndRow($column, 17 + $length_needed * 2)->setValue($stat->hammingDistanceTarget->stats->sigma);
	$sheet->getCellByColumnAndRow($column, 18 + $length_needed * 2)->setValue(join(', ', $stat->hammingDistanceTarget->stats->moda));
	$sheet->getCellByColumnAndRow($column, 19 + $length_needed * 2)->setValue($stat->hammingDistanceTarget->stats->variationFactor);
	$sheet->getCellByColumnAndRow($column, 20 + $length_needed * 2)->setValue($stat->hammingDistanceTarget->stats->minValue . ', ' . $stat->hammingDistanceTarget->stats->maxValue);
	
	for($length_i = 0; $length_i <= $length_needed; $length_i++) {
		$sheet->getCellByColumnAndRow($column, 21 + $length_needed * 2 + $length_i)->setValue(isset($stat->hammingDistanceWild->distancesEnumerated->$length_i) ? $stat->hammingDistanceWild->distancesEnumerated->$length_i / $hdwmax : 0);
	}
	$sheet->getCellByColumnAndRow($column, 22 + $length_needed * 3)->setValue($stat->hammingDistanceWild->stats->mathExpectation);
	$sheet->getCellByColumnAndRow($column, 23 + $length_needed * 3)->setValue($stat->hammingDistanceWild->stats->sigma);
	$sheet->getCellByColumnAndRow($column, 24 + $length_needed * 3)->setValue(join(', ', $stat->hammingDistanceWild->stats->moda));
	$sheet->getCellByColumnAndRow($column, 25 + $length_needed * 3)->setValue($stat->hammingDistanceWild->stats->variationFactor);
	$sheet->getCellByColumnAndRow($column, 26 + $length_needed * 3)->setValue($stat->hammingDistanceWild->stats->minValue . ', ' . $stat->hammingDistanceWild->stats->maxValue);
	
	$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(50);
}
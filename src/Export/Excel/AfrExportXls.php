<?php

namespace Autoframe\Core\Export\Excel;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\String\Excel\AfrStrExcel;

class AfrExportXls extends AfrSingletonAbstractClass
{
	function export(
		array  $aSheetsRowsCells,
		string $save_file_to_path = null,
		bool   $setCellValueExplicit = true,
		string $title = 'Data feed export',
		string $firm = 'AutoFrame',
		string $format = 'xls',
		bool   $die = false,
		       $password = null
	)
	{
		//prea($sheets_rows_cels);
		if (!class_exists('PHPExcel')) {
			$class_path = THF_FUNC . 'helper/PHPExcel/Classes/PHPExcel.php';

			if (is_file($class_path) && is_readable($class_path)) {
				require_once($class_path);
			} else {
				echo '<h1>PHPExcel Class not found! Export failed!</h1>';
				if ($die) {
					die();
				}
				return false;
			}
		}


		$objPHPExcel = new PHPExcel();
		$objPHPExcel->getProperties()->setCreator($firm)
			->setLastModifiedBy($firm)
			->setTitle($title)
			->setSubject($title)
			->setDescription($firm)
			->setKeywords($firm)
			->setCategory($firm);


		$s = 0;
		foreach ($aSheetsRowsCells as $sheet_name => $rows) {
			if ($s > 0) {
				$objPHPExcel->createSheet();
			}
			$i = 0;
			foreach ($rows as $row) {//$i=>
				$j = 0;
				foreach ($row as $cell) { // $j=>
					if ($setCellValueExplicit) {
						$objPHPExcel->setActiveSheetIndex($s)->setCellValueExplicit(
							AfrStrExcel::num2excel($j) . ($i + 1), $cell, PHPExcel_Cell_DataType::TYPE_STRING);
					} else {
						$objPHPExcel->setActiveSheetIndex($s)->setCellValue(
							AfrStrExcel::num2excel($j) . ($i + 1), $cell);
					}
					$j++;
				}
				$i++;
			}
			if (strlen($sheet_name) > 1) {
				$objPHPExcel->getActiveSheet()->setTitle($sheet_name);
			}
			$s++;
		}
		$objPHPExcel->setActiveSheetIndex(0);


		if ($password) {
			//$format='xlsx'; //https://stackoverflow.com/questions/21639731/protect-the-excel-file-using-phpexcel
			if (is_string($password)) {
				$objPHPExcel->getSecurity()->setLockWindows(true);
				$objPHPExcel->getSecurity()->setLockStructure(true);
				$objPHPExcel->getSecurity()->setWorkbookPassword($password);
			} elseif (is_array($password)) {
				/*$objPHPExcel->getActiveSheet()->getProtection()->setSheet(true);
				$objPHPExcel->getActiveSheet()->getProtection()->setSort(true);
				$objPHPExcel->getActiveSheet()->getProtection()->setInsertRows(true);
				$objPHPExcel->getActiveSheet()->getProtection()->setFormatCells(true);
				$objPHPExcel->getActiveSheet()->getProtection()->setPassword('password');*/
				die('not implemented password for each sheet');
			}
		}


		if ($save_file_to_path) {
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, ($format == 'xls' ? 'Excel5' : 'Excel2007'));
			$objWriter->save(rtrim($save_file_to_path, '\/') . '/' . $title . '.xls' . ($format == 'xls' ? '' : 'x'));
		} else { //download

			// Redirect output to a client's web browser (Excel5)
			header(($format == 'xls' ? 'Content-Type: application/vnd.ms-excel' : "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"));
			header('Content-Disposition: attachment;filename="' . urlencode($title . '.' . $format) . '"');

			// If you're serving to IE over SSL, then the following may be needed
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: no-cache');
			header('Expires: Wed, 18 Nov 1981 09:12:00 GMT');
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, ($format == 'xls' ? 'Excel5' : 'Excel2007'));
			$objWriter->save('php://output');

		}
		if ($die) {
			die($die);
		}
		return $save_file_to_path;
	}

}
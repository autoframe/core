<?php

namespace Autoframe\Core\Upload;

//TODO:
class MultipleFiles {
	function normalizeazaFILES(): array
	{
		if (empty($_FILES['file']['tmp_name'])) {
			return [];
		}
		$aFiles = [];
		foreach ($_FILES['file']['tmp_name'] as $inputName => $aIndividualFiles) {
			foreach ($aIndividualFiles as $i => $sTempName) {
				$aFile = [
					'name' => $_FILES['file']['name'][$inputName][$i] ?? null,
					'type' => $_FILES['file']['type'][$inputName][$i] ?? null,
					'tmp_name' => $_FILES['file']['tmp_name'][$inputName][$i] ?? null,
					'error' => (int)($_FILES['file']['error'][$inputName][$i] ?? 99),
					'size' => $_FILES['file']['size'][$inputName][$i] ?? 0,
				];
				if (
					strlen($aFile['name']) > 3 && //exista filename
					$aFile['error'] === 0 && // zero este cod pentru lipsa eroare
					$aFile['size'] > 0 && // nu este gol
					$aFile['tmp_name'] && is_file($aFile['tmp_name']) && //file exista din directorul de temp
					( //extensie:
						$aFile['type'] === 'application/mp4' ||
						substr($aFile['type'], 0, 6) === 'video/' ||
						substr($aFile['type'], 0, 6) === 'image/'
					)

				) {
					$aFiles[$inputName][$i] = $aFile; //adaug in lista de valide
				}
			}
		}
		return $aFiles;
	}

}
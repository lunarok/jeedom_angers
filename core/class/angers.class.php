<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class angers extends eqLogic {
	public static function cronDaily() {
    $eqLogics = eqLogic::byType('angers', true);
    foreach ($eqLogics as $eqLogic) {
      $eqLogic->refreshAll();
    }
  }

	public function loadCmdFromConf($type) {
		if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
			return;
		}
		$content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
		if (!is_json($content)) {
			return;
		}
		$device = json_decode($content, true);
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
				|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
					$cmd = $liste_cmd;
					break;
				}
			}
			if ($cmd == null || !is_object($cmd)) {
				$cmd = new angersCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
			}
		}
	}

	public function postAjax() {
		$this->loadCmdFromConf('angers');
		$this->refreshAll();
	}

	public function refreshAll() {
		$this->poubelleOM();
		$this->poubelleTri();
		$this->resultCrue();
		$this->resultPollen();
		$this->resultPolluant();
	}

	public function findDates($_result) {
		$time = strtotime('today');
		$tomorrow = strtotime('+ 1 day');
		$return['previous'] = 0;
		$return['next'] = strtotime('+1 year');
		$return['binary'] = 0;
		$return['tomorrow'] = 0;
		foreach ($_result['records'] as $elt) {
			$actual = strtotime($elt['fields']['date_collecte']);
			if (($actual < $time) && ($actual > $previous)) {
				$return['previous'] = $elt['fields']['date_collecte'];
			}
			if (($actual > $time) && ($actual < $next)) {
				$return['next'] = $elt['fields']['date_collecte'];
				$return['exception'] = ($elt['fields']['exception'] == 'N') ? 0:1;
			}
			if ($actual == $time) {
				$return['binary'] = 1;
			}
			if ($tomorrow == $actual) {
				$return['tomorrow'] = 1;
			}
		}
		return $return;
	}

	public function pollen($_result) {
		foreach ($_result['records'] as $elt) {
			$return[$elt['fields']['name']] = $elt['fields']['state'];
			$return['text' . $elt['fields']['name']] = $elt['fields']['value'];
		}
		return $return;
	}

	public function polluant($_result) {
		foreach ($_result['records'] as $elt) {
			$return[$elt['fields']['sous_indice_2_polluant_name']] = $elt['fields']['sous_indice_2_valeur'];
			$return['text' . $elt['fields']['sous_indice_2_polluant_name']] = $elt['fields']['sous_indice_2_indice'];
			$return['couleur' . $elt['fields']['sous_indice_2_polluant_name']] = $elt['fields']['sous_indice_2_couleur'];
		}
		return $return;
	}

	public function vigicrue($_result) {
		$return['hauteur'] = $_result['records'][0]['fields']['hauteur'];
		return $return;
	}

	public function callOpenData($_url) {
		$request_http = new com_http($_url);
    $request_http->setNoReportError(true);
    $return = $request_http->exec(15,2);
		return json_decode($return, true);
	}

	public function poubelleOM() {
		$data = $this->findDates($this->callOpenData($this->getConfiguration('urlOM')));
		$this->checkAndUpdateCmd('om:previous', $data['previous']);
		$this->checkAndUpdateCmd('om:next', $data['next']);
		$this->checkAndUpdateCmd('om:binary', $data['binary']);
		$this->checkAndUpdateCmd('om:tomorrow', $data['tomorrow']);
		$this->checkAndUpdateCmd('om:exception', $data['exception']);
	}

	public function poubelleTri() {
		$data = $this->findDates($this->callOpenData($this->getConfiguration('urlTri')));
		$this->checkAndUpdateCmd('tri:previous', $data['previous']);
		$this->checkAndUpdateCmd('tri:next', $data['next']);
		$this->checkAndUpdateCmd('tri:binary', $data['binary']);
		$this->checkAndUpdateCmd('tri:tomorrow', $data['tomorrow']);
		$this->checkAndUpdateCmd('tri:exception', $data['exception']);
	}

	public function resultCrue() {
		$data = $this->vigicrue($this->callOpenData('https://data.angers.fr/api/records/1.0/search/?dataset=vigicrues-hauteurs-et-debits-des-cours-deau&q=&rows=1&facet=station_id&facet=lbstationhydro&facet=cdcommune&facet=timestamp&facet=cdzonehydro&facet=nom_com&facet=nom_reg&facet=nom_epci&facet=nom_dep'));
		$this->checkAndUpdateCmd('crue:niveau', $data['niveau']);
	}

	public function resultPolluant() {
		$data = $this->polluant($this->callOpenData($this->getConfiguration('polluant')));
		$this->checkAndUpdateCmd('polluant:PM25', $data['PM25']);
		$this->checkAndUpdateCmd('polluant:PM10', $data['PM10']);
		$this->checkAndUpdateCmd('polluant:NO2', $data['NO2']);
		$this->checkAndUpdateCmd('polluant:SO2', $data['SO2']);
		$this->checkAndUpdateCmd('polluant:O3', $data['O3']);
		$this->checkAndUpdateCmd('polluant:textPM25', $data['textPM25']);
		$this->checkAndUpdateCmd('polluant:textPM10', $data['textPM10']);
		$this->checkAndUpdateCmd('polluant:textNO2', $data['textNO2']);
		$this->checkAndUpdateCmd('polluant:textSO2', $data['textSO2']);
		$this->checkAndUpdateCmd('polluant:textO3', $data['textO3']);
		$this->checkAndUpdateCmd('polluant:couleurPM25', $data['couleurPM25']);
		$this->checkAndUpdateCmd('polluant:couleurPM10', $data['couleurPM10']);
		$this->checkAndUpdateCmd('polluant:couleurNO2', $data['couleurNO2']);
		$this->checkAndUpdateCmd('polluant:couleurSO2', $data['couleurSO2']);
		$this->checkAndUpdateCmd('polluant:couleurO3', $data['couleurO3']);
	}

	public function resultPollen() {
		$data = $this->polluant($this->callOpenData('https://data.angers.fr/api/records/1.0/search/?dataset=pollinarium-sentinelle-angers&q=&rows=20&facet=group'));
		$this->checkAndUpdateCmd('pollen:Armoise', $data['Armoise']);
		$this->checkAndUpdateCmd('pollen:Saule', $data['Saule']);
		$this->checkAndUpdateCmd('pollen:Fromental', $data['Fromental']);
		$this->checkAndUpdateCmd('pollen:Noisetier', $data['Noisetier']);
		$this->checkAndUpdateCmd('pollen:Frêne', $data['Frêne']);
		$this->checkAndUpdateCmd('pollen:Flouve', $data['Flouve']);
		$this->checkAndUpdateCmd('pollen:Bouleau', $data['Bouleau']);
		$this->checkAndUpdateCmd('pollen:Chêne rouvre', $data['Chêne rouvre']);
		$this->checkAndUpdateCmd('pollen:Plantain', $data['Plantain']);
		$this->checkAndUpdateCmd('pollen:Dactyle', $data['Dactyle']);
		$this->checkAndUpdateCmd('pollen:Bouleau', $data['Bouleau']);
		$this->checkAndUpdateCmd('pollen:Aulne', $data['Aulne']);
		$this->checkAndUpdateCmd('pollen:Cyprès', $data['Cyprès']);
		$this->checkAndUpdateCmd('pollen:Peuplier', $data['Peuplier']);
		$this->checkAndUpdateCmd('pollen:Ray-grass', $data['Ray-grass']);
		$this->checkAndUpdateCmd('pollen:Vulpin', $data['Vulpin']);
		$this->checkAndUpdateCmd('pollen:Houlque', $data['Houlque']);
		$this->checkAndUpdateCmd('pollen:Fléole', $data['Fléole']);
		$this->checkAndUpdateCmd('pollenText:Armoise', $data['textArmoise']);
		$this->checkAndUpdateCmd('pollenText:Saule', $data['textSaule']);
		$this->checkAndUpdateCmd('pollenText:Fromental', $data['textFromental']);
		$this->checkAndUpdateCmd('pollenText:Noisetier', $data['textNoisetier']);
		$this->checkAndUpdateCmd('pollenText:Frêne', $data['textFrêne']);
		$this->checkAndUpdateCmd('pollenText:Flouve', $data['textFlouve']);
		$this->checkAndUpdateCmd('pollenText:Bouleau', $data['textBouleau']);
		$this->checkAndUpdateCmd('pollenText:Chêne rouvre', $data['textChêne rouvre']);
		$this->checkAndUpdateCmd('pollenText:Plantain', $data['textPlantain']);
		$this->checkAndUpdateCmd('pollenText:Dactyle', $data['textDactyle']);
		$this->checkAndUpdateCmd('pollenText:Bouleau', $data['textBouleau']);
		$this->checkAndUpdateCmd('pollenText:Aulne', $data['textAulne']);
		$this->checkAndUpdateCmd('pollenText:Cyprès', $data['textCyprès']);
		$this->checkAndUpdateCmd('pollenText:Peuplier', $data['textPeuplier']);
		$this->checkAndUpdateCmd('pollenText:Ray-grass', $data['textRay-grass']);
		$this->checkAndUpdateCmd('pollenText:Vulpin', $data['textVulpin']);
		$this->checkAndUpdateCmd('pollenText:Houlque', $data['textHoulque']);
		$this->checkAndUpdateCmd('pollenText:Fléole', $data['textFléole']);
	}

}

class angersCmd extends cmd {

}
?>

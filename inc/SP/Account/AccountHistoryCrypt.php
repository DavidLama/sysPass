<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Account;

use SP\Config\ConfigDB;
use SP\Core\OldCrypt;
use SP\Core\Exceptions\SPException;
use SP\Log\Email;
use SP\Log\Log;
use SP\Storage\DB;
use SP\Storage\QueryData;
use SP\Util\Checks;

/**
 * Class AccountHistoryCrypt
 *
 * @package SP\Account
 */
class AccountHistoryCrypt
{
    /**
     * Actualiza las claves de todas las cuentas con la clave maestra actual
     * usando nueva encriptación.
     *
     * @param $currentMasterPass
     * @return bool
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function updateOldPass(&$currentMasterPass)
    {
        $accountsOk = [];
        $demoEnabled = Checks::demoIsEnabled();
        $errorCount = 0;

        $Log = new Log();
        $LogMessage = $Log->getLogMessage();
        $LogMessage->setAction(__('Actualizar Clave Maestra (H)', false));
        $LogMessage->addDescription(__('Inicio', false));
        $Log->writeLog(true);

        if (!OldCrypt::checkCryptModule()) {
            $LogMessage->addDescription(__('Error en el módulo de encriptación', false));
            $Log->setLogLevel(Log::ERROR);
            $Log->writeLog();
            return false;
        }

        $accountsPass = $this->getAccountsPassData();

        if (count($accountsPass) === 0) {
            $LogMessage->addDescription(__('Error al obtener las claves de las cuentas', false));
            $Log->setLogLevel(Log::ERROR);
            $Log->writeLog();
            return false;
        }

        $currentMPassHash = ConfigDB::getValue('masterPwd');

        $AccountDataBase = new \stdClass();
        $AccountDataBase->id = 0;
        $AccountDataBase->pass = '';
        $AccountDataBase->iv = '';
        $AccountDataBase->hash = Crypt\Hash::hashKey($currentMasterPass);

        foreach ($accountsPass as $account) {
            $AccountData = clone $AccountDataBase;

            $AccountData->id = $account->acchistory_id;

            // No realizar cambios si está en modo demo
            if ($demoEnabled) {
                $accountsOk[] = $account->acchistory_id;
                continue;
            } elseif ($account->acchistory_mPassHash !== $currentMPassHash) {
                $errorCount++;
                $LogMessage->addDetails(__('La clave maestra del registro no coincide', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
                continue;
            } elseif (empty($account->acchistory_pass)) {
                $LogMessage->addDetails(__('Clave de cuenta vacía', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
                continue;
            } elseif (strlen($account->acchistory_IV) < 32) {
                $LogMessage->addDetails(__('IV de encriptación incorrecto', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
            }

            $decryptedPass = OldCrypt::getDecrypt($account->acchistory_pass, $account->acchistory_IV, $currentMasterPass);

            $securedKey = Crypt\Crypt::makeSecuredKey($currentMasterPass);

            $AccountData->pass = Crypt\Crypt::encrypt($decryptedPass, $securedKey);
            $AccountData->iv = $securedKey;

            try {
                $Account = new AccountHistory();
                $Account->updateAccountPass($AccountData);

                $accountsOk[] = $account->acchistory_id;
            } catch (SPException $e) {
                $errorCount++;
                $LogMessage->addDetails(__('Fallo al actualizar la clave del histórico', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
            }
        }

        $LogMessage->addDetails(__('Cuentas actualizadas', false), implode(',', $accountsOk));
        $LogMessage->addDetails(__('Errores', false), $errorCount);
        $Log->writeLog();

        Email::sendEmail($LogMessage);

        return true;
    }

    /**
     * Obtener los datos relativos a la clave de todas las cuentas.
     *
     * @return array Con los datos de la clave
     */
    protected function getAccountsPassData()
    {
        $query = /** @lang SQL */
            'SELECT acchistory_id, acchistory_name, acchistory_pass, acchistory_IV, acchistory_mPassHash FROM accHistory';

        $Data = new QueryData();
        $Data->setQuery($query);

        return DB::getResultsArray($Data);
    }

    /**
     * Actualiza las claves de todas las cuentas con la nueva clave maestra.
     *
     * @param $currentMasterPass
     * @param $newMasterPass
     * @return bool
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function updatePass($currentMasterPass, $newMasterPass)
    {
        $accountsOk = [];
        $demoEnabled = Checks::demoIsEnabled();
        $errorCount = 0;

        $Log = new Log();
        $LogMessage = $Log->getLogMessage();
        $LogMessage->setAction(__('Actualizar Clave Maestra (H)', false));
        $LogMessage->addDescription(__('Inicio', false));
        $Log->writeLog(true);

        $accountsPass = $this->getAccountsPassData();

        if (count($accountsPass) === 0) {
            $LogMessage->addDescription(__('Error al obtener las claves de las cuentas', false));
            $Log->setLogLevel(Log::ERROR);
            $Log->writeLog();
            return false;
        }

        $currentMPassHash = ConfigDB::getValue('masterPwd');

        $AccountDataBase = new \stdClass();
        $AccountDataBase->id = 0;
        $AccountDataBase->pass = '';
        $AccountDataBase->iv = '';
        $AccountDataBase->hash = Crypt\Hash::hashKey($newMasterPass);

        foreach ($accountsPass as $account) {
            $AccountData = clone $AccountDataBase;

            $AccountData->id = $account->acchistory_id;

            // No realizar cambios si está en modo demo
            if ($demoEnabled) {
                $accountsOk[] = $account->acchistory_id;
                continue;
            } elseif ($account->acchistory_mPassHash !== $currentMPassHash) {
                $errorCount++;
                $LogMessage->addDetails(__('La clave maestra del registro no coincide', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
                continue;
            } elseif (empty($account->acchistory_pass)) {
                $LogMessage->addDetails(__('Clave de cuenta vacía', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
                continue;
            } elseif (strlen($account->acchistory_IV) < 32) {
                $LogMessage->addDetails(__('IV de encriptación incorrecto', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
            }

            $currentSecuredKey = Crypt\Crypt::unlockSecuredKey($account->acchistory_IV, $currentMasterPass);
            $decryptedPass = Crypt\Crypt::decrypt($account->acchistory_pass, $currentSecuredKey);

            $newSecuredKey = Crypt\Crypt::makeSecuredKey($newMasterPass);
            $AccountData->acchistory_pass = Crypt\Crypt::encrypt($decryptedPass, $newSecuredKey);
            $AccountData->acchistory_IV = $newSecuredKey;

            try {
                $Account = new AccountHistory();
                $Account->updateAccountPass($AccountData);

                $accountsOk[] = $account->acchistory_id;
            } catch (SPException $e) {
                $errorCount++;
                $LogMessage->addDetails(__('Fallo al actualizar la clave del histórico', false), sprintf('%s (%d)', $account->acchistory_name, $account->acchistory_id));
            }
        }

        $LogMessage->addDetails(__('Cuentas actualizadas', false), implode(',', $accountsOk));
        $LogMessage->addDetails(__('Errores', false), $errorCount);
        $Log->writeLog();

        Email::sendEmail($LogMessage);

        return true;
    }
}
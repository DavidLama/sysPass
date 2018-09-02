<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Modules\Web\Controllers\Helpers\Account;

use SP\Core\Acl\Acl;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Crypt;
use SP\Core\Crypt\Session as CryptSession;
use SP\DataModel\AccountPassData;
use SP\Modules\Web\Controllers\Helpers\HelperBase;
use SP\Modules\Web\Controllers\Helpers\HelperException;
use SP\Services\Crypt\MasterPassService;
use SP\Util\ImageUtil;

/**
 * Class AccountPasswordHelper
 *
 * @package SP\Modules\Web\Controllers\Helpers
 */
final class AccountPasswordHelper extends HelperBase
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @param AccountPassData $accountData
     *
     * @param bool            $useImage
     *
     * @return array
     * @throws HelperException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Repositories\NoSuchItemException
     * @throws \SP\Services\ServiceException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    public function getPasswordView(AccountPassData $accountData, bool $useImage)
    {
        $this->checkActionAccess();

        $this->view->addTemplate('viewpass');

        $this->view->assign('header', __('Clave de Cuenta'));
        $this->view->assign('isImage', (int)$useImage);

        $pass = $this->getPasswordClear($accountData);

        if ($useImage) {
            $imageUtil = $this->dic->get(ImageUtil::class);

            $this->view->assign('login', $imageUtil->convertText($accountData->getLogin()));
            $this->view->assign('pass', $imageUtil->convertText($pass));
        } else {
            $this->view->assign('login', $accountData->getLogin());
            $this->view->assign('pass', htmlentities($pass));
        }

        $this->view->assign('sk', $this->context->generateSecurityKey());

        return [
            'useimage' => $useImage,
            'html' => $this->view->render()
        ];
    }

    /**
     * @throws HelperException
     */
    private function checkActionAccess()
    {
        if (!$this->acl->checkUserAccess(ActionsInterface::ACCOUNT_VIEW_PASS)) {
            throw new HelperException(__u('No tiene permisos para acceder a esta cuenta'));
        }
    }

    /**
     * Returns account's password
     *
     * @param AccountPassData $accountData
     *
     * @return string
     * @throws HelperException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Repositories\NoSuchItemException
     * @throws \SP\Services\ServiceException
     */
    public function getPasswordClear(AccountPassData $accountData)
    {
        $this->checkActionAccess();

        if (!$this->dic->get(MasterPassService::class)->checkUserUpdateMPass($this->context->getUserData()->getLastUpdateMPass())) {
            throw new HelperException(__('Clave maestra actualizada') . '<br>' . __('Reinicie la sesión para cambiarla'));
        }

        return trim(Crypt::decrypt($accountData->getPass(), $accountData->getKey(), CryptSession::getSessionKey($this->context)));
    }

    protected function initialize()
    {
        $this->acl = $this->dic->get(Acl::class);
    }
}
<?php

/*
 * This file is part of the DevShop package.
 *
 * (c) Jon Pugh <jon@thinkdrop.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DevShop\Component\GitHubTools;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use DevShop\Component\GitHubTools\GitHubDeploymentsCommand;

class ComposerCommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return array(
          new GitHubDeploymentsCommand()
        );
    }
}

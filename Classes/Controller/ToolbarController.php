<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 31.01.18
 * Time: 22:39
 */

namespace SUDHAUS7\Guard7\Controller;

use SUDHAUS7\Guard7\Adapter\ConfigurationAdapter;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ToolbarController implements ToolbarItemInterface
{
    
    /**
     * @var array
     */
    protected $extConfig;
    /**
     * @var IconFactory
     * @inject
     * @api
     */
    protected $iconFactory;
    
    
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $pageRenderer = $this->getPageRenderer();
        $configadapter = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        $inlinecode = '';
        if ($configadapter->config['usejavascriptdecodinginbackend']) {
            $inlinecode .= 'var sudhaus7guard7data_DISABLED = false;';
        } else {
            $inlinecode .= 'var sudhaus7guard7data_DISABLED = true;';
        }
        if ($configadapter->config['populatebeuserprivatekeytofrontend']) {
            $inlinecode .= 'var sudhaus7guard7data_privatekeytofrontend = true;';
        } else {
            $inlinecode .= 'var sudhaus7guard7data_privatekeytofrontend = false';
        }
        $pageRenderer->addJsInlineCode(
            __METHOD__,
            $inlinecode
        );
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Guard7/Toolbar');
        $pageRenderer->addCssFile('../' . ExtensionManagementUtility::siteRelPath('guard7') . 'Resources/Public/Css/styles.css');
    }
    
    /**
     * Returns current PageRenderer
     *
     * @return PageRenderer
     */
    protected function getPageRenderer()
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }
    
    public function checkAccess()
    {
        return true;
    }
    
    public function getItem()
    {
        $opendocsMenu = array();
        $opendocsMenu[] = '<span class="t3-icon fa fa-lock" title="Guard7">' . '</span>';
        return implode(LF, $opendocsMenu);
    }
    
    public function hasDropDown()
    {
        return true;
    }
    
    public function getDropDown()
    {
        $dropdown = [];
        
        $dropdown[] = '<ul class="dropdown-list">';

        $dropdown[] = '<li class="clearKey"><button>'.LocalizationUtility::translate('toolbar.deactivatekey', 'guard7').'</button></li>';
        $dropdown[] = '<li class="newkey-elem"><textarea name="newkey"></textarea><br/><button>'.LocalizationUtility::translate('toolbar.activatekey', 'guard7').'</button></li>';
        
        $dropdown[] = '</ul>';
        
        
        return implode(LF, $dropdown);
    }
    
    public function getAdditionalAttributes()
    {
        return array();
    }
    
    public function getIndex()
    {
        return 5;
    }
}

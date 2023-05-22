<?php

/**
 * Action component of diagrams plugin
 *
 * This handles general operations independent of the configured mode
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Innovakom + CosmoCode <dokuwiki@cosmocode.de>
 */
class action_plugin_diagrams_action extends DokuWiki_Action_Plugin
{
    /** @var helper_plugin_diagrams */
    protected $helper;

    /**@inheritDoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'addJsinfo');
        $controller->register_hook('MEDIAMANAGER_STARTED', 'AFTER', $this, 'addJsinfo');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'checkConf');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleCache');

        $this->helper = plugin_load('helper', 'diagrams');
    }

    /**
     * Add data to JSINFO
     *
     * full service URL
     * digram mode
     * security token used for uploading
     *
     * @param Doku_Event $event DOKUWIKI_STARTED|MEDIAMANAGER_STARTED
     */
    public function addJsinfo(Doku_Event $event)
    {
        global $JSINFO;
        $JSINFO['sectok'] = getSecurityToken();
        $JSINFO['plugins']['diagrams'] = [
            'service_url' => $this->getConf('service_url'),
            'mode' => $this->getConf('mode'),
        ];
    }

    /**
     * Check if DokuWiki is properly configured to handle SVG diagrams
     *
     * @param Doku_Event $event DOKUWIKI_STARTED
     */
    public function checkConf(Doku_Event $event)
    {
        $mime = getMimeTypes();
        if (!array_key_exists('svg', $mime) || $mime['svg'] !== 'image/svg+xml') {
            msg($this->getLang('missingConfig'), -1);
        }
    }

    /**
     * Save the PNG cache of a diagram
     *
     * @param Doku_Event $event AJAX_CALL_UNKNOWN
     */
    public function handleCache(Doku_Event $event)
    {
        if ($event->data !== 'plugin_diagrams_savecache') return;
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;

        $svg = $INPUT->str('svg'); // raw svg
        $png = $INPUT->str('png'); // data uri

        if (!checkSecurityToken()) {
            http_status(403);
            return;
        }

        if (!$this->helper->isDiagram($svg)) {
            http_status(400);
            return;
        }

        if(!preg_match('/^data:image\/png;base64,/', $png)) {
            http_status(400);
            return;
        }
        $png = base64_decode(explode(',',$png)[1]);

        if(substr($png, 1, 3) !== 'PNG') {
            http_status(400);
            return;
        }

        $cacheName = getCacheName($svg, '.diagrams.png');
        if(io_saveFile($cacheName, $png)) {
            echo 'OK';
        } else {
            http_status(500);
        }
    }
}

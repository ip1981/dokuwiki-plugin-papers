<?php
/**
 * DokuWiki Plugin papers (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Igor Pashev <pashev.igor@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

global $conf;
if (!defined('PAPERS_DATADIR')) define('PAPERS_DATADIR', DOKU_INC . $conf['savedir'] . '/media/');


require_once (DOKU_INC . 'inc/pageutils.php');
require_once (DOKU_INC . 'inc/parserutils.php');
require_once (DOKU_PLUGIN . 'syntax.php');
require_once (DOKU_PLUGIN . 'papers/bibtex.php');

class syntax_plugin_papers extends DokuWiki_Syntax_Plugin
{
    function getType() { return 'protected'; }

    function getPType() { return 'block'; } // http://www.dokuwiki.org/devel:syntax_plugins#ptype

    function getSort() { return 102; }


    function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('<bibtex>(?=.*</bibtex>)', $mode, 'plugin_papers');
        $this->Lexer->addEntryPattern('<papers>(?=.*</papers>)', $mode, 'plugin_papers');
    }

    function postConnect()
    {
        $this->Lexer->addExitPattern('</bibtex>', 'plugin_papers');
        $this->Lexer->addExitPattern('</papers>', 'plugin_papers');
    }

    function handle($match, $state, $pos, &$handler)
    {
        static $tag;
        switch ($state)
        {
            case DOKU_LEXER_ENTER :
                preg_match('/<(\w+)>/', $match, $tmp);
                $tag = $tmp[1];
                unset($tmp);
                return array($state, $tag, '', array());
 
            case DOKU_LEXER_UNMATCHED :
                if ($tag === 'papers') // Parse <papers>...</papers>
                {
                    $spec = array();
                    $fields = preg_split('/\s*\n\s*/u', $match);
                    foreach ($fields as &$f)
                    {
                        if (preg_match('/\s*(\w+?)\s*=\s*(.*)/u', $f, $m))
                        {
                            $spec[$m[1]] = $m[2];
                        }
                    }
                    $source = '';
                    if (isset($spec['source']))
                    {
                        $source = wikiFN($spec['source']);
                        unset($spec['source']);
                    }
                    else
                    {
                        $source = wikiFN($this->getConf('bibtex'));
                    }

                    $options = array();
                    foreach(array('raw', 'byyear') as $o)
                    {
                        if (isset($spec[$o]))
                        {
                            $options[$o] = $spec[$o];
                            unset($spec[$o]);
                        }
                    }

                    $bibtex = (!isset($options['byyear']) || $options['byyear']) ?
                        new BibtexParserTeam() : new BibtexParserWorker();
                    $bibtex->read_file($source);
                    unset($source);
                    $bibtex->select($spec);
                    unset($spec);
                    $bibtex->sort();
                    return array($state, $tag, $bibtex, $options);
                }
                elseif ($tag === 'bibtex') // Parse <bibtex>...</bibtex>
                {
                    $bibtex = new BibtexParserTeam();
                    $bibtex->read_text($match);
                    $bibtex->select();
                    $bibtex->sort();
                    return array($state, $tag, $bibtex, array());
                }
        
            case DOKU_LEXER_EXIT :
                return array($state, $tag, '', array());
        }
        return array();
    }

    function render($mode, &$renderer, $data)
    {
        if ($mode != 'xhtml') return false;
        list($state, $tag, $bibtex, $options) = $data;
        switch ($state)
        {
            case DOKU_LEXER_ENTER: 
                break;

            case DOKU_LEXER_UNMATCHED:
                $renderer->doc .= $this->format_bibtex($bibtex, $options);
                break;

            case DOKU_LEXER_EXIT:
                break;
        }
        return true;
    }

    function wikirender($text)
    {
        return p_render('xhtml', p_get_instructions($text), $info);
    }


    function format_bibtex(&$bibtex, $options = array())
    {
        $res = '';
        $year = ''; $year_prev = '';
        $type = ''; $type_prev = '';
        $in_list = false;
        foreach ($bibtex->SELECTION as &$entry)
        {
            // <papers> tag results in just a list of papers
            if (!isset($options['raw']) || !$options['raw'])
            {
                 // No 'Year' title for papers of a worker (team member)
                if (!isset($options['byyear']) || ($options['byyear']))
                {
                    // Remember about ranges: 2009-2010 - take the last year
                    preg_match('/.*(\d{4})/u', $entry['year'], $matches);
                    $year = $matches[1];
                    if ($year < $this->getConf('year_min'))
                        break;

                    if ($year !== $year_prev)
                    {
                        if ($in_list)
                        {
                            $res .= "</ol>\n";
                            $in_list = false;
                        }

                        $year_prev = $year;
                        $type_prev = '';
                        $res .= "<h2 class=\"sectionedit2\">$year "
                            . $this->getLang('year') . "</h2>\n";
                    }
                }

                $type = $entry['entry'];
                if ($type !== $type_prev)
                {
                    if ($in_list)
                    {
                        $res .= "</ol>\n\n\n";
                        $in_list = false;
                    }

                    $type_prev = $type;
                    $res .= '<h3 class="sectionedit3">' . $this->getLang($type) . "</h3>\n";
                }
            }

            if (!$in_list)
            {
                $res .= "<ol>\n";
                $in_list = true;
            }
            $res .= '<li class="level1"><div class="li" style="padding:0.3em;">' . $entry['html'];
            
            $links = array();
            foreach ($this->getConf('filetypes') as $type)
            {
                $file = $this->getConf('papers_ns') . '/' . $entry['id'] . '.' . mb_strtolower($type);
                if (file_exists(PAPERS_DATADIR . $file))
                {
                    $size = round(filesize(PAPERS_DATADIR . $file) / 1024) . ' ' . $this->getLang('KiB');
                    $links[] = '{{:' . preg_replace('/\//u', ':', $file) . "|$type $size}}";
                }
            }
            if (!empty($links))
            {
                $link_text = $this->wikirender('(' . implode(' | ', $links) . ')');
                $link_text = preg_replace('/<\/?p>/u', '', $link_text);
                $link_text = preg_replace('/\s+(\d+)\s+/u', '&nbsp;\1&nbsp;', $link_text);
                $res .= '<span class="noprint">' . $link_text . '</span>';
            }
            $res .=  "</div></li>\n";
        }


        $res .= "</ol>\n";
        return $res;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:


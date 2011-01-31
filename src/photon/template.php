<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Photon Templating Engine.
 *
 * The templating engine is very small and directly reuse the PHP
 * parser. A template is compiled as a PHP file and provides a class
 * with a render() method.
 */
namespace photon\template;

use photon\config\Container as Conf;
use photon\template\compiler\Compiler as Compiler;

class Exception extends \Exception {};

/**
 * Render a template file.
 */
class Renderer
{
    public $tpl = '';
    public $folders = array();
    public $cache = '';
    public $compiled_template = '';
    public $template_content = '';
    public $context = null;
    public $class = '';
    public $compiler = null;

    /**
     * Constructor.
     *
     * If the folder names are not provided, default to
     * Conf::f('template_folders', null)
     * If the cache folder name is not provided, it will default to
     * Conf::f('tmp_folder', '/tmp')
     *
     * @param string Template name.
     * @param string Template folder paths (null)
     * @param string Cache folder name (null)
     * @param $options Extra options for the compiler (array())
     */
    function __construct($template, $folders=null, $cache=null, $options=array())
    {
        $this->tpl = $template;
        $this->folders = (null === $folders) 
            ? Conf::f('template_folders') : $folders;
        $this->cache = (null === $cache)
            ? Conf::f('tmp_folder') : $cache;

        list($tmpl_path, $tmpl_uid) = $this->getCompiledTemplateName();
        $this->compiled_template = $tmpl_path;
        $this->class = 'Template_' . $tmpl_uid;

        if (!class_exists('\photon\template\compiled\\' . $this->class, false)) {
            if (!file_exists($this->compiled_template) || Conf::f('debug')) {
                $this->compiler = new Compiler($this->tpl, $this->folders, $options);
                $this->getCompiledTemplateContent();
                $this->write($this->compiled_template);
            }
            include_once $this->compiled_template;
        }
    }

    /**
     * Render the template with the given context and return the content.
     *
     * @param $c Object Context (null)
     */
    function render($c=null)
    {
        $this->context = (null === $c) ? new Context() : $c;
        ob_start();
        try {
            call_user_func(array('\photon\template\compiled\\' . $this->class, 
                                 'render'), $this->context);
        } catch (\Exception $e) {
            ob_clean();
            throw $e;
        }
        $a = ob_get_contents();
        ob_end_clean();

        return $a;
    }

    /**
     * Get the full name of the compiled template.
     *
     * Ends with .phps to prevent execution from outside if the cache folder
     * is not secured but to still have the syntax higlightings by the tools
     * for debugging.
     *
     * @return string Full path to the compiled template
     */
    function getCompiledTemplateName()
    {
        // The compiled template not only depends on the file but also
        // on the possible folders in which it can be found.
        $tmp = var_export($this->folders, true);
        $uid = md5($tmp . $this->tpl);

        return array($this->cache . '/photon_template_compiled_' . $uid . '.phps', 
                     $uid);
    }

    /**
     * Run the compiler and get the content.
     */
    function getCompiledTemplateContent()
    {
        // We wrap the raw PHP in the right class, with the right
        // namespace etc. We need to also load the localization
        // classes.
        $this->template_content = '<?php
// Automatically generated by Photon at: ' . date('c') . '
// Photon - http://photon-project.com
namespace photon\template\compiled;
class ' . $this->class . '
{
    public static function render($t) 
    {
        ?>' . $this->compiler->getCompiledTemplate() . '<?php 
    } 
}';
    }

    /**
     * Write the compiled template in the cache folder.
     * Throw an exception if it cannot write it.
     *
     * @param $file Where to write the template
     * @return bool Success in writing
     */
    function write($file) 
    {
        if (false === @file_put_contents($file, $this->template_content, LOCK_EX)) {
            throw new Exception(sprintf(__('Cannot write the compiled template: %s'), $file));
        }

        return true;
    }

    public static function markSafe($string)
    {
        return new SafeString($string, true);
    }

    /**
     * Safely echo an object/string in the template.
     *
     * @param $mixed
     * @return void
     */
    public static function secho($mixed)
    {
        echo (!is_object($mixed) || 'photon\template\SafeString' != get_class($mixed)) ?
            htmlspecialchars($mixed) : $mixed->value;
    }

    /**
     * Safely return an object/string in the template.
     *
     * @param $mixed
     * @return string String safe to display in an HTML page
     */
    public static function sreturn($mixed)
    {
        return (!is_object($mixed) || 'photon\template\SafeString' != get_class($mixed)) ?
            htmlspecialchars($mixed) : $mixed->value;
    }
}

/**
 * A string already escaped to display in a template.
 */
class SafeString
{
    public $value = '';

    function __construct($mixed, $safe=false)
    {
        if (is_object($mixed) and 'photon\template\SafeString' == get_class($mixed)) {
            $this->value = $mixed->value;
        } else {
            $this->value = ($safe) ? $mixed : htmlspecialchars($mixed);
        }
    }

    function __toString()
    {
        return $this->value;
    }

    public static function markSafe($string)
    {
        return new SafeString($string, true);
    }
}

/**
 * Class storing the data that are then used in the template.
 */
class Context 
{
    public $_vars;

    function __construct($vars=array())
    {
        $this->_vars = new ContextVars($vars);
    }

    function get($var)
    {
        if (isset($this->_vars[$var])) {

            return $this->_vars[$var];
        }

        return '';
    }

    function set($var, $value)
    {
        $this->_vars[$var] = $value;
    }
}

/**
 * Special array where the keyed indexes can be accessed as properties.
 */
class ContextVars extends \ArrayObject
{
    function __get($prop)
    {
        return (isset($this[$prop])) ? $this[$prop] : '';
    }

    function __set($prop, $value)
    {
        $this[$prop] = $value;
    }

    function __toString()
    {
        return var_export($this, true);
    }
}

/**
 * Default modifiers for the compilers.
 *
 * Each modifier is a static method.
 */
class Modifier
{
    /**
     * Set a string to be safe for display.
     *
     * @param $string String to be safe for display.
     * @return SafeString 
     */
    public static function safe($string)
    {
        return new SafeString($string, true);
    }

    /**
     * New line to <br /> returning a safe string.
     *
     * @param $mixed Input
     * @return string Safe to display in HTML.
     */
    public static function nl2br($mixed)
    {
        if (!is_object($mixed) || 'photon\template\SafeString' !== get_class($mixed)) {
            return Renderer::markSafe(\nl2br(htmlspecialchars($mixed)));
        } else {

            return Renderer::markSafe(\nl2br((string) $mixed));
        }
    }

    /**
     * Var export returning a safe string.
     *
     * @param mixed Input
     * @return string Safe to display in HTML.
     */
    public static function varExport($mixed)
    {
        return self::safe('<pre>' . esc(var_export($mixed, true)) . '</pre>');
    }


    /**
     * Hex encode an email excluding the "mailto:".
     */
    public static function safeEmail($email)
    {
        $email = chunk_split(bin2hex($email), 2, '%');
        $email = '%' . substr($email, 0, strlen($email) - 1);

        return self::safe($email);
    }

    /**
     * Returns the first item in the given array.
     *
     * @param array $array
     * @return mixed An empty string if $array is not an array.
     */
    public static function first($array)
    {
        $array = (array) $array;
        $result = \array_shift($array);

        return (null === $result) ? '' : $result;
    }

    /**
     * Returns the last item in the given array.
     *
     * @param array $array
     * @return mixed An empty string if $array is not an array.
     */
    public static function last($array)
    {
        $array = (array) $array;
        $result = \array_pop($array);

        return (null === $result) ? '' : $result;
    }

    // /**
    //  * Display the date in a "6 days, 23 hours ago" style.
    //  */
    // public static function Pluf_Template_dateAgo($date, $f='withal')
    // {
    //     Pluf::loadFunction('Pluf_Date_Easy');
    //     $date = Pluf_Template_dateFormat($date, '%Y-%m-%d %H:%M:%S');
    //     if ($f == 'withal') {

    //         return Pluf_Date_Easy($date, null, 2, __('now'));
    //     } else {

    //         return Pluf_Date_Easy($date, null, 2, __('now'), false);
    //     }
    // }

    // /**
    //  * Display the time in a "6 days, 23 hours ago" style.
    //  */
    // public static function Pluf_Template_timeAgo($date, $f="withal")
    // {
    //     Pluf::loadFunction('Pluf_Date_Easy');
    //     $date = Pluf_Template_timeFormat($date);
    //     if ($f == 'withal') {

    //         return Pluf_Date_Easy($date, null, 2, __('now'));
    //     } else {

    //         return Pluf_Date_Easy($date, null, 2, __('now'), false);
    //     }
    // }

    // /**
    //  * Modifier plugin: Convert the date from GMT to local and format it.
    //  *
    //  * This is used as all the datetime are stored in GMT in the database.
    //  *
    //  * @param string $date input date string considered GMT
    //  * @param string $format strftime format for output ('%b %e, %Y')
    //  * @return string date in localtime
    //  */
    // public static function dateFormat($date, $format='%b %e, %Y') 
    // {
    //     if (substr(PHP_OS, 0, 3) == 'WIN') {
    //         $_win_from = array ('%e', '%T', '%D');
    //         $_win_to = array ('%#d', '%H:%M:%S', '%m/%d/%y');
    //         $format	= \str_replace($_win_from, $_win_to, $format);
    //     }
    //     $date = date('Y-m-d H:i:s', strtotime($date . ' GMT'));

    //     return strftime($format, strtotime($date));
    // }

    // /**
    //  * Modifier plugin: Format a unix time.
    //  *
    //  * Warning: date format is directly to be used, not consideration of
    //  * GMT or local time.
    //  *
    //  * @param int $time  input date string considered GMT
    //  * @param string $format strftime format for output ('Y-m-d H:i:s')
    //  * @return string formated time
    //  */
    // public static function timeFormat($time, $format='Y-m-d H:i:s') 
    // {
    //     return \date($format, $time);
    // }
}

/**
 * Escape a string in a binary safe way.
 *
 * htmlspecialchars will break if you pass in a badly encoded
 * string. This escaping function will not break.
 */
function esc($string) 
{
    return \str_replace(array('&',     '"',      '<',    '>'),
                        array('&amp;', '&quot;', '&lt;', '&gt;'),
                        (string) $string);
}


/**
 * Special htmlspecialchars that can handle the objects.
 *
 * @param string String proceeded by htmlspecialchars
 * @return string String like if htmlspecialchars was not applied
 */
function htmlspecialchars($string)
{
    return \htmlspecialchars((string) $string, ENT_COMPAT, 'UTF-8');
}



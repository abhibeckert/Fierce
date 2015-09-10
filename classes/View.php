<?php

/**
 * 
 * Fierce Web Framework
 * https://github.com/abhibeckert/Fierce
 *
 * This is free and unencumbered software released into the public domain.
 * For more information, please refer to http://unlicense.org
 * 
 */

namespace Fierce;

class View
{
  protected static $tagStack = [];
  
  protected static $currentForm = null;
  
  protected static $rowStack = [];
  
  protected static $vars = [];
  
  protected static $scriptUrls = [];
  protected static $cssUrls = [];
  
  protected static $twig = false;
  
  static protected function initTwig()
  {
    if (self::$twig) {
      return;
    }
    
    $loader = new \Twig_Loader_Filesystem([
      BASE_PATH . 'views/',
      FIERCE_PATH . 'views/'
    ]);
    
    if (!F_DISABLE_CACHE) {
      $cacheDir = BASE_PATH . 'tmp/twig_cache/';
      if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
      }
    }
    
    self::$twig = new \Twig_Environment($loader, [
      'cache' => F_DISABLE_CACHE ? false : $cacheDir,
      'strict_variables' => true
    ]);
    
    self::$twig->addTokenParser(new Tag\NavParser());
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\IncludeCssNode'));
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\IncludeScriptNode'));
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\FieldNode'));
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\FieldRowNode'));
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\WysiwygNode'));
    self::$twig->addTokenParser(new Tag\Parser('Fierce\\Tag\\WysiwygRowNode'));
    self::$twig->addTokenParser(new Tag\FormParser());
    
    self::$twig->addFilter(new \Twig_SimpleFilter('ltrim', 'ltrim'));
  }
  
  static public function main($templateView, $contentView = false, $vars = array())
  {
    self::initTwig();
    
    $twigVars = array_merge(
      get_defined_constants(),
      [
        'loggedInUser' => Auth::loggedInUser(),
        'cssUrls' => self::$cssUrls,
        'scriptUrls' => self::$scriptUrls
      ],
      self::$vars,
      $vars
    );
    

    if ($contentView) {
      $twigVars['contentViewHtml'] = self::$twig->render($contentView, $twigVars);
    } else if (!isset($vars['contentViewHtml'])) {
      $twigVars['contentViewHtml'] = false;
    }
    
    print self::$twig->render($templateView, $twigVars);
  }
  
  static public function renderTpl($contentView, $vars)
  {
    $blockedVars = ['templateView', 'contentView', 'vars', 'var', 'value'];
    foreach (self::$vars as $var => $value) {
      if (in_array($var, $blockedVars)) {
        continue;
      }
      $$var = $value;
    }
    foreach ($vars as $var => $value) {
      if (in_array($var, $blockedVars)) {
        continue;
      }
      $$var = $value;
    }
    
    $contentTpl = BASE_PATH . 'views/' . $contentView;
    if (!file_exists($contentTpl)) {
      $contentTpl = FIERCE_PATH . 'views/' . $contentView;
    }
    if (!file_exists($contentTpl)) {
      throw new \exception('Can\'t find view ' . $contentView);
    }
    
    require($contentTpl);
  }
  
  static public function set($key, $value)
  {
    self::$vars[$key] = $value;
  }
  
  static public function includeView($view, $vars = array())
  {
    foreach (self::$vars as $var => $value) {
      $$var = $value;
    }
    foreach ($vars as $var => $value) {
      $$var = $value;
    }
    
    $tpl = BASE_PATH . 'views/' . $view;
    if (!file_exists($tpl)) {
      $tpl = FIERCE_PATH . 'views/' . $view;
    }
    if (!file_exists($tpl)) {
      throw new \exception('Can\'t find view ' . $view);
    }
    
    require($tpl);
  }
  
  static public function tag($name, $params)
  {
    $html = "<$name";
    foreach ($params as $param => $value) {
      $valueEscaped = htmlspecialchars($value);
      
      $html .= " $param=\"$valueEscaped\"";
    }
    $html .= '>';
    
    print $html;
  }
  
  static public function openTag($name, $params=[])
  {
    self::$tagStack[] = $name;
    
    self::tag($name, $params);
  }
  
  static public function closeTag($name)
  {
    $expectedName = array_pop(self::$tagStack);
    if ($expectedName != $name) {
      if (!$expectedName) {
        $expectedName = 'null';
      }
      
      throw new \exception("Unexpected close tag '$name', expected '$expectedName'.");
    }
    
    print "</$name>";
  }
  
  protected static function formFieldValue($name)
  {
    if (!isset(self::$currentForm['data'])) {
      return '';
    }
    
    $keyPath = array($name);
    while (($startNextKey = strpos(end($keyPath), '[')) !== false) {
      $lastComponent = array_pop($keyPath);
      
      $endNextKey = strpos($lastComponent, ']', $startNextKey);
      
      
      $keyPath[] = substr($lastComponent, 0, $startNextKey);
      $keyPath[] = substr($lastComponent, $startNextKey + 1, $endNextKey - $startNextKey - 1) . substr($lastComponent, $endNextKey + 1);
    }
    
    $value = self::$currentForm['data'];
    foreach ($keyPath as $key) {
      $value = @$value->$key;
    }
    
    return $value;
  }
  
  static public function form($params)
  {
    // default values
    $params['method'] = 'post';
    
    // prepare for later use
    self::$currentForm = $params;
    if (!isset(self::$currentForm['displayNames'])) {
      if (isset(self::$currentForm['data']) && property_exists(self::$currentForm['data'], 'displayNames')) {
        self::$currentForm['displayNames'] = self::$currentForm['data']->displayNames;
      } else {
        self::$currentForm['displayNames'] = array();
      }
    }
    
    // generate html
    if (isset($params['data'])) {
      unset($params['data']);
    }
    
    
    self::openTag('form', $params, true);
  }
  
  static public function closeForm()
  {
    self::$currentForm = null;
    
    self::closeTag('form');
  }
  
  static public function openRow(&$params)
  {
    self::$rowStack[] = $params;
    
    unset($params['note']);
    
    $name = $params['name'];
    
    if (isset($params['displayName'])) {
      $displayName = $params['displayName'];
      unset($params['displayName']);
    } else {
      $displayName = @self::$currentForm['displayNames'][$name];
      
      if (!$displayName) {
        $displayName =  ucwords(str_replace('_', ' ', $name));
      }
    }
    
    $rowClass = 'row';
    if (isset($params['class'])) {
      foreach (explode(' ', $params['class']) as $class) {
        $rowClass .= " {$class}_row";
      }
    }
    
    print "<div class=\"$rowClass\">";
    print "<label for=\"{$name}_field\">" . htmlspecialchars($displayName) . '</label>' . "\n";
    print "<div class=\"field\">";
  }
  
  static public function closeRow()
  {
    $params = array_pop(self::$rowStack);
    
    print "</div>\n";
    if (isset($params['note'])) {
      print '<span class="note">' . htmlspecialchars($params['note']) . '</span>';
    }
    
    print '</div>';
  }
  
  static public function field($params)
  {
    if (!isset($params['id'])) {
      $id = $params['name'];
      $id = str_replace('[', '_', $id);
      $id = str_replace(']', '', $id);
      $id .= '_field';
      $params['id'] = $id;
    }
    if (!isset($params['type'])) {
      $params['type'] = 'text';
    }
    
    if (!isset($params['value'])) {
      $params['value'] = self::formFieldValue($params['name']);
    }
    
    self::tag('input', $params);
  }
  
  static public function fieldRow($params)
  {
    self::openRow($params);
    
    self::field($params);
    
    self::closeRow();
  }
  
  static public function photoField($params)
  {
    View::addScript(FIERCE_SRC . '/scripts/photo-field.controller.js');
    
    $params['type'] = 'file';
    $params['class'] = trim('photo_upload ' . @$params['class']);
    if (!isset($params['id'])) {
      $id = $params['name'];
      $id = str_replace('[', '_', $id);
      $id = str_replace(']', '', $id);
      $id .= '_upload_field';
      $params['id'] = $id;
    }
    
    self::field($params);
    
    self::field(['type' => 'hidden', 'name' => $params['name']]);
    
    self::openTag('div', ['id' => $params['name'] . '_preview_wrapper', 'class' => 'photo_preview_wrapper']);
    
    $src = '';
    if (!isset($params['value'])) {
      $src = self::formFieldValue($params['name'] . '_src');
    }
    self::tag('img', ['id' => $params['name'] . '_preview', 'src' => $src]);
    
    self::closeTag('div');
  }
  
  static public function photoFieldRow($params)
  {
    self::openRow($params);
    
    self::photoField($params);
    
    self::closeRow();
  }
  
  static public function tagField($params)
  {
    View::addScript(FIERCE_SRC . 'scripts/tag-field.controller.js');
    
    $params['class'] = trim('tag_field large ' . @$params['class']);
    
    $inputParams = $params;
    unset($inputParams['options']);
    
    self::field($inputParams);
    
    self::openTag('ul', ['id' => $params['name'] . '_tags', 'class' => 'tag_list']);
    
    $options = [];
    if (isset($params['options'])) {
      $options = $params['options'];
    }
    
    foreach ($options as $option) {
      self::openTag('li');
      print htmlspecialchars($option);
      self::closeTag('li');
    }
    
    self::closeTag('ul');
  }
  
  static public function tagFieldRow($params)
  {
    self::openRow($params);
    
    self::tagField($params);
    
    self::closeRow();
  }
  
  static public function select($params)
  {
    if (!isset($params['id'])) {
      $id = $params['name'];
      $id = str_replace('[', '_', $id);
      $id = str_replace(']', '', $id);
      $id .= '_field';
      $params['id'] = $id;
    }
    
    
    $options = $params['options'];
    unset($params['options']);
    $value = self::formFieldValue($params['name']);
    
    self::openTag('select', $params);
    $useNameAsValue = null;
    foreach ($options as $optionValue => $name) {
      if ($useNameAsValue === null) {
        $useNameAsValue = $optionValue === 0;
      }
      
      if ($useNameAsValue) {
        $optionValue = $name;
      }
      
      if ($optionValue == $value) {
        self::openTag('option', ['value' => $optionValue, 'selected' => 'selected']);
      } else {
        self::openTag('option', ['value' => $optionValue]);
      }
      
      print htmlspecialchars($name);
      
      self::closeTag('option');
    }
    self::closeTag('select');
  }
  
  static public function selectRow($params)
  {
    self::openRow($params);
    
    self::select($params);
    
    self::closeRow();
  }
  
  static public function textarea($params)
  {
    if (!isset($params['id'])) {
      $id = $params['name'];
      $id = str_replace('[', '_', $id);
      $id = str_replace(']', '', $id);
      $id .= '_field';
      $params['id'] = $id;
    }
    
    
    $value = self::formFieldValue($params['name']);
    
    self::openTag('textarea', $params);
    
    print htmlspecialchars($value);
    
    self::closeTag('textarea');
  }
  
  static public function textareaRow($params)
  {
    self::openRow($params);
    
    self::textarea($params);
    
    self::closeRow();
    
    if (strpos(@$params['class'], 'wysiwyg') !== false) {
      self::addScript(FIERCE_SRC . 'scripts/wysiwyg.controller.js');
      self::addScript(FIERCE_SRC . 'third-party/ckeditor/ckeditor.js');
    }
  }
  
  static public function addScript($scriptUrl)
  {
    if (in_array($scriptUrl, self::$scriptUrls)) {
      return;
    }
    
    self::$scriptUrls[] = $scriptUrl;
  }
  
  static public function addCss($cssUrl)
  {
    if (in_array($cssUrl, self::$cssUrls)) {
      return;
    }
    
    self::$cssUrls[] = $cssUrl;
  }
  
  static public function thumbnail($imageUrl, $w, $h, $allowCrop)
  {
    $image = new Image($imageUrl);
    
    $thumbUrl = $image->createThumbnail($w, $h, $allowCrop, false, $thumbWidth, $thumbHeight);
    $thumb2xUrl = $image->createThumbnail($w * 2, $h * 2, $allowCrop, false, $thumb2xWidth, $thumb2xHeight);
    
    $srcsetHtml = '';
    if ($thumb2xWidth > $thumbWidth && pathinfo(BASE_PATH . $imageUrl, PATHINFO_EXTENSION) != 'svg') {
      $srcsetHtml = "srcset=\"$thumbUrl 1x,$thumb2xUrl 2x\"";
    }
    
    return "<img src=\"$thumbUrl\" width=\"$thumbWidth\" height=\"$thumbHeight\"$srcsetHtml>";
  }
}

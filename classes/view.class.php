<?

/**
 * 
 * Fierce Web Framework
 * https://github.com/abhibeckert/Fierce
 *
 * This is free and unencumbered software released into the public domain.
 * For more information, please refer to http://unlicense.org
 * 
 */

class View
{
  protected static $tagStack = [];
  
  protected static $currentForm = null;
  
  protected static $rowStack = [];
  
  protected static $vars = [];
  
  protected static $scriptUrls = [];
  protected static $cssUrls = [];
  
  public static function main($templateView, $contentView = false, $vars = array())
  {
    foreach (self::$vars as $var => $value) {
      $$var = $value;
    }
    foreach ($vars as $var => $value) {
      $$var = $value;
    }
    
    $mainTpl = BASE_PATH . 'views/' . $templateView;
    if (!file_exists($mainTpl)) {
      $mainTpl = BASE_PATH . 'fierce/views/' . $templateView;
    }
    if (!file_exists($mainTpl)) {
      throw new exception('Can\'t find view ' . $templateView);
    }
    
    if ($contentView) {
      $contentTpl = BASE_PATH . 'views/' . $contentView;
      if (!file_exists($contentTpl)) {
        $contentTpl = BASE_PATH . 'fierce/views/' . $contentView;
      }
      if (!file_exists($contentTpl)) {
        throw new exception('Can\'t find view ' . $contentView);
      }
      
      ob_start();
      require($contentTpl);
      $contentViewHtml = ob_get_clean();
    } else if (!isset($vars['contentViewHtml'])) {
      $contentViewHtml = false;
    }
    $scriptUrls = self::$scriptUrls;
    $cssUrls = self::$cssUrls;
    
    require($mainTpl);
  }
  
  public static function set($key, $value)
  {
    self::$vars[$key] = $value;
  }
  
  public static function includeView($view, $vars = array())
  {
    foreach (self::$vars as $var => $value) {
      $$var = $value;
    }
    foreach ($vars as $var => $value) {
      $$var = $value;
    }
    
    $tpl = BASE_PATH . 'views/' . $view;
    if (!file_exists($tpl)) {
      $tpl = BASE_PATH . 'fierce/views/' . $view;
    }
    if (!file_exists($tpl)) {
      throw new exception('Can\'t find view ' . $view);
    }
    
    require($tpl);
  }
  
  public static function tag($name, $params)
  {
    $html = "<$name";
    foreach ($params as $param => $value) {
      $valueEscaped = htmlspecialchars($value);
      
      $html .= " $param=\"$valueEscaped\"";
    }
    $html .= '>';
    
    print $html;
  }
  
  public static function openTag($name, $params)
  {
    self::$tagStack[] = $name;
    
    self::tag($name, $params);
  }
  
  public static function closeTag($name)
  {
    $expectedName = array_pop(self::$tagStack);
    if ($expectedName != $name) {
      if (!$expectedName) {
        $expectedName = 'null';
      }
      
      throw new exception("Unexpected close tag '$name', expected '$expectedName'.");
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
  
  public static function form($params)
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
  
  public static function closeForm()
  {
    self::$currentForm = null;
    
    self::closeTag('form');
  }
  
  public static function openRow(&$params)
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
  
  public static function closeRow()
  {
    $params = array_pop(self::$rowStack);
    
    print "</div>\n";
    if (isset($params['note'])) {
      print '<span class="note">' . htmlspecialchars($params['note']) . '</span>';
    }
    
    print '</div>';
  }
  
  public static function field($params)
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
  
  public static function fieldRow($params)
  {
    self::openRow($params);
    
    self::field($params);
    
    self::closeRow();
  }
  
  public static function select($params)
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
  
  public static function selectRow($params)
  {
    self::openRow($params);
    
    self::select($params);
    
    self::closeRow();
  }
  
  public static function textarea($params)
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
  
  public static function textareaRow($params)
  {
    self::openRow($params);
    
    self::textarea($params);
    
    self::closeRow();
    
    if (strpos(@$params['class'], 'wysiwyg') !== false) {
      self::addScript('fierce/scripts/wysiwyg.controller.js');
    }
  }
  
  public static function addScript($scriptUrl)
  {
    if (in_array($scriptUrl, self::$scriptUrls)) {
      return;
    }
    
    self::$scriptUrls[] = $scriptUrl;
  }
  
  public static function addCss($cssUrl)
  {
    if (in_array($cssUrl, self::$cssUrls)) {
      return;
    }
    
    self::$cssUrls[] = $cssUrl;
  }
  
  public static function thumbnail($imageUrl, $w, $h, $allowCrop)
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

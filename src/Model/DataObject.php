<?php

namespace Levtechdev\Simpaas\Model;

/**
 * Universal data container with array access implementation
 */
class DataObject implements \ArrayAccess
{
    const DATA_KEY_PATH_DELIMITER = '.';

    /**
     * Object attributes
     *
     * @var array
     */
    protected $data = [];

    /**
     * Setter/Getter underscore transformation cache
     *
     * @var array
     */
    protected static $underscoreCache = [];

    /**
     * Data changes flag (true after setData|unsetData call)
     * @var $hasDataChange bool
     */
    protected $hasDataChanges = false;

    /**
     * Constructor
     *
     * By default is looking for first argument as array and assigns it as object attributes
     * This behavior may change in child classes
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Factory create method for DataObject instance
     *
     * @param array $data
     *
     * @return static
     */
    public function factoryCreate(array $data = []): static
    {
        return new static($data);
    }

    /**
     * Check if initial object data was changed.
     *
     * Initial data is coming to object constructor.
     * Flag value should be set up to true after any external data changes
     *
     * @return bool
     */
    public function hasDataChanges(): bool
    {
        return $this->hasDataChanges;
    }

    /**
     * @param bool $flag
     *
     * @return $this
     */
    public function setHasDataChanges(bool $flag): static
    {
        $this->hasDataChanges = $flag;

        return $this;
    }

    /**
     * Add data to the object.
     *
     * Retains previous data in the object.
     *
     * @param array $arr
     *
     * @return $this
     */
    public function addData(array $arr)
    {
        foreach ($arr as $index => $value) {
            $this->setData($index, $value);
        }

        return $this;
    }

    /**
     * Object data getter
     *
     * If $key is not defined will return all the data as an array
     * Otherwise it will return value of the element specified by $key
     *
     * It is possible to use keys like a.b.c for access nested array data
     *
     * @param string     $key
     *
     * @return mixed
     */
    public function getData($key = '')
    {
        if ($key === '') {

            return $this->data;
        }

        if (empty($this->data) || !is_array($this->data)) {

            return null;
        }

        // Process a.b.c key as ['a']['b']['c']
        if (strpos($key, self::DATA_KEY_PATH_DELIMITER)) {
            $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $key);
            $data = $this->data;

            foreach ($keys as $key) {
                if ((array)$data === $data && key_exists($key, $data)) {
                    $data = $data[$key];
                    array_shift($subKeys);
                } elseif ($data instanceof DataObject) {
                    // maintain relative path for Data Object data elements
                    return $data->getData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys));
                } else {
                    return null;
                }

            }
        } else {
            $data = null;
            if (key_exists($key, $this->data)) {

                $data = $this->data[$key];
            }
        }

        return $data;
    }

    /**
     * If $key is empty, checks whether there's any data in the object
     * Otherwise checks if the specified attribute is set
     *
     * Method consider the path as chain of keys: a.b.c => ['a']['b']['c']
     *
     * @param string $path
     *
     * @return bool
     */
    public function hasData($path = '')
    {

        if ($path === '') {

            return !empty($this->data);
        }

        if (empty($this->data) || !is_array($this->data)) {

            return false;
        }

        // Process a.b.c key as ['a']['b']['c']
        // @todo most likely this check is not needed, as next explode() call will return one key which then will be checked
        if (!strpos($path, self::DATA_KEY_PATH_DELIMITER)) {

            return key_exists($path, $this->data);
        }

        $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $path);
        $data = $this->data;
        foreach ($keys as $key) {
            if ((array)$data === $data && key_exists($key, $data)) {
                $data = $data[$key];
                array_shift($subKeys);
            } elseif ($data instanceof DataObject) {

                // maintain relative path for Data Object data elements
                return $data->hasData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys));
            } else {

                return false;
            }
        }

        return true;
    }

    /**
     * Overwrite data in the object.
     *
     * The $key parameter can be string or array.
     * If $key is string, the attribute value will be overwritten by $value
     *
     * Method consider the path as chain of keys: a.b.c => ['a']['b']['c']
     *
     * If $key is an array, it will overwrite all the data in the object
     *
     * @param string|array $key
     * @param mixed        $value
     *
     * @return $this
     */
    public function setData($key, $value = null)
    {
        if ($key === (array)$key) {
            if ($this->data !== $key) {
                $this->hasDataChanges = true;
            }
            $this->data = $key;

            return $this;
        }

        if (!strpos($key, self::DATA_KEY_PATH_DELIMITER)) {
            if (!key_exists($key, $this->data) || $this->data[$key] != $value) {
                $this->hasDataChanges = true;
            }
            $this->data[$key] = $value;

            return $this;
        }

        // Process a.b.c key as ['a']['b']['c']
        $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $key);
        $currentValue = &$this->data;
        foreach ($keys as $key) {
            if ($currentValue instanceof DataObject) {
                // maintain relative path for Data Object data elements
                $value = $currentValue->setData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys), $value);
                break;
            } elseif (!is_array($currentValue) || !key_exists($key, $currentValue)) {
                $currentValue[$key] = [];
            }

            $currentValue = &$currentValue[$key];
            array_shift($subKeys);
        }

        if ($value instanceof DataObject) {
            if ($value->hasDataChanges()) {
                $this->hasDataChanges = true;
            }
        } else {
            if ($currentValue !== $value) {
                $this->hasDataChanges = true;
            }
        }
        $currentValue = $value;
        unset($currentValue);

        return $this;
    }

    /**
     * Unset data from the object
     *
     * Method consider the path as chain of keys: a.b.c => ['a']['b']['c']
     *
     * @param null|string|array $key
     *
     * @return $this
     */
    public function unsetData($key = null)
    {
        if ($key === null) {
            $this->setData([]);

            return $this;
        }

        if ($key === (array)$key) {
            foreach ($key as $element) {
                $this->unsetData($element);
            }

            return $this;
        }

        if (!strpos($key, self::DATA_KEY_PATH_DELIMITER)) {
            if (key_exists($key, $this->data)) {
                $this->hasDataChanges = true;
            }
            unset($this->data[$key]);

            return $this;
        }

        // Process a.b.c key as ['a']['b']['c']
        $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $key);
        $unsetKey = end($subKeys);

        $currentValue = &$this->data;
        foreach ($keys as $currentKey) {
            if ($currentValue instanceof DataObject) {
                // maintain relative path for Data Object data elements
                $currentValue->unsetData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys));
                if ($currentValue->hasDataChanges()) {
                    $this->hasDataChanges = true;
                }

                return $this;
            } elseif (is_array($currentValue) && key_exists($currentKey, $currentValue)) {
                if ($currentKey === $unsetKey) {
                    unset($currentValue[$unsetKey]);
                    $this->hasDataChanges = true;
                } else {
                    $currentValue = &$currentValue[$currentKey];
                    array_shift($subKeys);
                }
            } else {
                break;
            }
        }

        return $this;
    }

    /**
     * Set specified data only if it was not set yet
     *
     * Method consider the path as chain of keys: a.b.c => ['a']['b']['c']
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setDataIfNotExist($key, $value = null)
    {
        if (!$this->hasData($key)) {
            $this->setData($key, $value);
        }

        return $this;
    }

    /**
     * Append specified value to an array of values in object data array
     *
     * Method consider the path as chain of keys: a.b.c => ['a']['b']['c']
     *
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function appendData($key, $value)
    {
        if (!strpos($key, self::DATA_KEY_PATH_DELIMITER)) {
            if (!key_exists($key, $this->data)) {
                $this->data[$key] = [];
            }
            // Convert non-array key to an array
            if (!is_array($this->data[$key])) {
                $this->data[$key] = [$this->data[$key]];
            }
            $this->data[$key][] = $value;
            $this->hasDataChanges = true;

            return $this;
        }

        // Process a.b.c key as ['a']['b']['c']
        $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $key);
        $currentValue = &$this->data;
        foreach ($keys as $k) {
            if ($currentValue instanceof DataObject) {
                // maintain relative path for Data Object data elements
                $value = $currentValue->appendData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys), $value);
                break;
            } elseif (!is_array($currentValue) || !key_exists($k, $currentValue)) {
                $currentValue[$k] = [];
            }

            $currentValue = &$currentValue[$k];
            array_shift($subKeys);
        }

        if ($value instanceof DataObject) {
            $currentValue = $value;
        } else {
            if (!is_array($currentValue)) {
                $currentValue = [$currentValue];
            }

            $currentValue[] = $value;
        }

        $this->hasDataChanges = true;
        unset($currentValue);

        return $this;
    }

    /**
     * Set object data with calling setter method
     *
     * @param string $key
     * @param mixed  $args
     *
     * @return $this
     */
    public function setDataUsingMethod($key, $args = [])
    {
        $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        $this->{$method}($args);

        return $this;
    }

    /**
     * Get object data by key with calling getter method
     *
     * @param string $key
     * @param mixed  $args
     *
     * @return mixed
     */
    public function getDataUsingMethod($key, $args = null)
    {
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

        return $this->{$method}($args);
    }

    /**
     * Convert array of object data to array with keys requested in $keys array
     *
     * @param array $keys array of required keys
     *
     * @return array
     */
    public function toArray(array $keys = [])
    {
        if (empty($keys)) {

            return $this->data;
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getData($key);
        }

        return $result;
    }

    /**
     * The "__" style wrapper for toArray method
     *
     * @param  array $keys
     *
     * @return array
     */
    public function convertToArray(array $keys = [])
    {
        return $this->toArray($keys);
    }

    /**
     * Convert object data to JSON
     *
     * @param array $keys array of required keys
     *
     * @return bool|string
     * @throws \InvalidArgumentException
     */
    public function toJson(array $keys = [])
    {
        $data = $this->toArray($keys);

        return json_encode($data);
    }

    /**
     * The "__" style wrapper for toJson
     *
     * @param array $keys
     *
     * @return bool|string
     * @throws \InvalidArgumentException
     */
    public function convertToJson(array $keys = [])
    {
        return $this->toJson($keys);
    }

    /**
     * Convert object data into string with predefined format
     *
     * Will use $format as an template and substitute {{key}} for attributes
     *
     * @param string $format
     *
     * @return string
     */
    public function toString($format = '')
    {
        if (empty($format)) {
            $result = implode(', ', $this->getData());
        } else {
            preg_match_all('/\{\{([a-z0-9_]+)\}\}/is', $format, $matches);
            foreach ($matches[1] as $var) {
                $format = str_replace('{{' . $var . '}}', $this->getData($var), $format);
            }
            $result = $format;
        }

        return $result;
    }

    /**
     * Set/Get attribute wrapper
     *
     * @param   string $method
     * @param   array  $args
     *
     * @return  mixed
     * @throws \InvalidArgumentException
     */
    public function __call($method, $args)
    {
        switch (substr($method, 0, 3)) {
            case 'get':
                $key = $this->_underscore(substr($method, 3));

                return $this->getData($key);
            case 'set':
                $key = $this->_underscore(substr($method, 3));
                $value = isset($args[0]) ? $args[0] : null;

                return $this->setData($key, $value);
            case 'uns':
                $key = $this->_underscore(substr($method, 3));

                return $this->unsetData($key);
            case 'has':
                $key = $this->_underscore(substr($method, 3));

                return $this->hasData($key);
        }

        throw new \BadMethodCallException(sprintf('Invalid method %s::%s()', get_class($this), $method));
    }

    /**
     * Checks whether the object is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        if (empty($this->data)) {

            return true;
        }

        return false;
    }

    /**
     * Converts field names for setters and getters
     *
     * $this->setMyField($value) === $this->setData('my_field', $value)
     * Uses cache to eliminate unnecessary preg_replace
     *
     * @param string $name
     *
     * @return string
     */
    protected function _underscore($name)
    {
        if (isset(self::$underscoreCache[$name])) {
            return self::$underscoreCache[$name];
        }
        $result = strtolower(trim(preg_replace('/([A-Z]|[0-9]+)/', "_$1", $name), '_'));
        self::$underscoreCache[$name] = $result;

        return $result;
    }

    /**
     * Convert object data into string with defined keys and values.
     *
     * Example: key1="value1" key2="value2" ...
     *
     * @param   array  $keys           array of accepted keys
     * @param   string $valueSeparator separator between key and value
     * @param   string $fieldSeparator separator between key/value pairs
     * @param   string $quote          quoting sign
     *
     * @return  string
     */
    public function serialize($keys = [], $valueSeparator = '=', $fieldSeparator = ' ', $quote = '"')
    {
        $data = [];
        if (empty($keys)) {
            $keys = array_keys($this->data);
        }

        foreach ($this->data as $key => $value) {
            if (in_array($key, $keys)) {
                $data[] = $key . $valueSeparator . $quote . $value . $quote;
            }
        }
        $res = implode($fieldSeparator, $data);

        return $res;
    }

    /**
     * Present object data as string in debug mode
     *
     * @param mixed $data
     * @param array &$objects
     *
     * @return array|string
     */
    public function debug($data = null, &$objects = [])
    {
        if ($data === null) {
            $hash = spl_object_hash($this);
            if (!empty($objects[$hash])) {

                return '*** RECURSION ***';
            }
            $objects[$hash] = true;
            $data = $this->getData();
        }
        $debug = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $debug[$key] = $value;
            } elseif (is_array($value)) {
                $debug[$key] = $this->debug($value, $objects);
            } elseif ($value instanceof DataObject) {
                $debug[$key . ' (' . get_class($value) . ')'] = $value->debug(null, $objects);
            }
        }

        return $debug;
    }

    /**
     * Implementation of \ArrayAccess::offsetSet()
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value)
    {
        $this->setData($offset, $value);
    }

    /**
     * Implementation of \ArrayAccess::offsetExists()
     *
     * @param string $offset
     *
     * @return bool
     * @link http://www.php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists($offset)
    {
        return $this->hasData($offset);
    }

    /**
     * Implementation of \ArrayAccess::offsetUnset()
     *
     * @param string $offset
     *
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset)
    {
        $this->unsetData($offset);
    }

    /**
     * Implementation of \ArrayAccess::offsetGet()
     *
     * @param string $offset
     *
     * @return mixed
     * @link http://www.php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset)
    {
        return $this->getData($offset);
    }

    /**
     * Compare to data arrays recursively and aggregate report on how values are different between arrays
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     */
    protected function compareData(array $array1, array $array2)
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            if (key_exists($key, $array2)) {
                if (is_object($value)) {
                    $value2 = is_object($array2[$key]) ? (array) $array2[$key] : $array2[$key];
                    $compared = $this->compareData((array)$value, $value2);
                    if ($compared) {
                        $result[$key] = $compared;
                    }
                }
                elseif (is_array($value)) {
                    $value2 = (array) $array2[$key];
                    $compared = $this->compareData($value, $value2);
                    if ($compared) {
                        $result[$key] = $compared;
                    }
                } else {
                    if ($value !== $array2[$key]) {
                        $result[$key]['was'] = $value;
                        $result[$key]['became'] = $array2[$key];
                    }
                }
            } else {
                $result[$key]['was'] = $value;
                $result[$key]['became'] = null;
            }
        }

        foreach ($array2 as $key => $value) {
            if (!key_exists($key, $array1)) {
                $result[$key]['was'] = null;
                $result[$key]['became'] = $value;
            }
        }

        return $result;
    }

    /**
     * Flatten multi dimensional data difference report into single level array with keys as path via delimeter
     *
     * This method is designated to process data produced by self::compareData() method
     *
     * @param array  $changes
     * @param string $prefix
     *
     * @return array
     */
    protected function flattenDataChangesReport($changes, $prefix = '')
    {
        $results = [];

        foreach ($changes as $key => $data) {
            if (is_array($data) && !key_exists('was', $data)) {
                $flattenResult = $this->flattenDataChangesReport($data, $prefix . $key . self::DATA_KEY_PATH_DELIMITER);
                if ($flattenResult) {
                    $results = array_merge($results, $flattenResult);
                }
            } else {
                if (is_array($data['was'])) {
                    foreach ($data['was'] as $field => $value) {
                        $became = null;
                        if (is_array($data['became'])) {
                            if (key_exists($field, $data['became'])) {
                                $became = $data['became'][$field];
                            }
                        } else {
                            $became = $data['became'];
                        }

                        $results[$prefix . $key . self::DATA_KEY_PATH_DELIMITER . $field] = [
                            'was'    => $value,
                            'became' => $became
                        ];
                    }
                } else if (is_array($data['became'])) {
                    foreach ($data['became'] as $field => $value) {
                        $was = null;
                        if (is_array($data['was'])) {
                            if (key_exists($field, $data['was'])) {
                                $was = $data['was'][$field];
                            }
                        } else {
                            $was = $data['was'];
                        }

                        $results[$prefix . $key . self::DATA_KEY_PATH_DELIMITER . $field] = [
                            'was'    => $was,
                            'became' => $value
                        ];
                    }
                } else {
                    $results[$prefix . $key] = $data;
                }
            }
        }

        return $results;
    }
}

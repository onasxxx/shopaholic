<?php

/**
 * Nette Framework
 *
 * Copyright (c) 2004, 2009 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://nettephp.com
 *
 * @copyright  Copyright (c) 2004, 2009 David Grudl
 * @license    http://nettephp.com/license  Nette license
 * @link       http://nettephp.com
 * @category   Nette
 * @package    Nette
 * @version    $Id: SmartCachingIterator.php 230 2009-03-19 12:16:22Z david@grudl.com $
 */



/**
 * Smarter caching interator.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2009 David Grudl
 * @package    Nette
 */
class SmartCachingIterator extends CachingIterator
{
	/** @var int */
	private $counter = 0;



	public function __construct($iterator)
	{
		if (is_array($iterator) || $iterator instanceof stdClass) {
			parent::__construct(new ArrayIterator($iterator), 0);

		} elseif ($iterator instanceof IteratorAggregate) {
			parent::__construct($iterator->getIterator(), 0);

		} elseif ($iterator instanceof Iterator) {
			parent::__construct($iterator, 0);

		} else {
			throw new InvalidArgumentException("Argument passed to " . __METHOD__ . " must be an array or interface Iterator provider, " . (is_object($iterator) ? get_class($iterator) : gettype($iterator)) ." given.");
		}
	}



	/**
	 * Is the current element the first one?
	 * @return bool
	 */
	public function isFirst()
	{
		return $this->counter === 1;
	}



	/**
	 * Is the current element the last one?
	 * @return bool
	 */
	public function isLast()
	{
		return !$this->hasNext();
	}



	/**
	 * Is the iterator empty?
	 * @return bool
	 */
	public function isEmpty()
	{
		return $this->counter === 0;
	}



	/**
	 * Is the counter odd?
	 * @return bool
	 */
	public function isOdd()
	{
		return $this->counter % 2 === 1;
	}



	/**
	 * Is the counter even?
	 * @return bool
	 */
	public function isEven()
	{
		return $this->counter % 2 === 0;
	}



	/**
	 * Returns the counter.
	 * @return int
	 */
	public function getCounter()
	{
		return $this->counter;
	}



	/**
	 * Returns the current index (counter - 1).
	 * @return int
	 * @deprecated
	 */
	public function getIndex()
	{
		return $this->counter > 0 ? $this->counter - 1 : FALSE;
	}



	/**
	 * Forwards to the next element.
	 * @return void
	 */
	public function next()
	{
		parent::next();
		if (parent::valid()) {
			$this->counter++;
		}
	}



	/**
	 * Rewinds the Iterator.
	 * @return void
	 */
	public function rewind()
	{
		parent::rewind();
		$this->counter = parent::valid() ? 1 : 0;
	}



	/********************* Nette\Object behaviour ****************d*g**/



	/**
	 * Call to undefined method.
	 *
	 * @param  string  method name
	 * @param  array   arguments
	 * @return mixed
	 * @throws MemberAccessException
	 */
	public function __call($name, $args)
	{
		return ObjectMixin::call($this, $name, $args);
	}



	/**
	 * Returns property value. Do not call directly.
	 *
	 * @param  string  property name
	 * @return mixed   property value
	 * @throws MemberAccessException if the property is not defined.
	 */
	public function &__get($name)
	{
		return ObjectMixin::get($this, $name);
	}



	/**
	 * Sets value of a property. Do not call directly.
	 *
	 * @param  string  property name
	 * @param  mixed   property value
	 * @return void
	 * @throws MemberAccessException if the property is not defined or is read-only
	 */
	public function __set($name, $value)
	{
		return ObjectMixin::set($this, $name, $value);
	}



	/**
	 * Is property defined?
	 *
	 * @param  string  property name
	 * @return bool
	 */
	public function __isset($name)
	{
		return ObjectMixin::has($this, $name);
	}



	/**
	 * Access to undeclared property.
	 *
	 * @param  string  property name
	 * @return void
	 * @throws MemberAccessException
	 */
	public function __unset($name)
	{
		$class = get_class($this);
		throw new MemberAccessException("Cannot unset the property $class::\$$name.");
	}


}

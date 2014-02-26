<?php

/**
 * @copyright Copyright (c) 2008-2013 by mTLD Top Level Domain Limited.  All rights reserved.
 * 
 * Portions copyright (c) 2008 by Argo Interactive Limited.
 * Portions copyright (c) 2008 by Nokia Inc.
 * Portions copyright (c) 2008 by Telecom Italia Mobile S.p.A.
 * Portions copyright (c) 2008 by Volantis Systems Limited.
 * Portions copyright (c) 2002-2008 by Andreas Staeding.
 * Portions copyright (c) 2008 by Zandan.
 * 
 */

/**
 * The IncorrectPropertyTypeException is thrown by the Api class.
 * When there is an attempt to fetch a property by type and the 
 * property is stored under a different type in the tree.
 * 
 * @author MTLD (dotMobi)
 * @version $Id: IncorrectPropertyTypeException.php 2830 2008-05-13 10:48:55Z ahopebailie $
 * 
 */
class Mobi_Mtld_Da_Exception_IncorrectPropertyTypeException extends Exception {}

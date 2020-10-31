<?php

namespace Sim\HitCounter\Exceptions;

use Exception;
use Sim\HitCounter\Interfaces\IDBException;

class BindValueException extends Exception implements IDBException
{

}
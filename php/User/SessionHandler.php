<?php namespace Surikat\User;
use SessionHandlerInterface;
interface SessionHandler extends SessionHandlerInterface{
	function touch($id);
}
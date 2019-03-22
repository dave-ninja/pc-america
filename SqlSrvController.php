<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SqlSrvController extends Controller
{
    public static function connection()
    {
	    $serverName = "localhost\\PCAMERICA"; //serverName\instanceName
	    $connectionInfo = array( "Database"=>"CRELiquorStore", "UID"=>"sa", "PWD"=>"admin");
	    $conn = sqlsrv_connect( $serverName, $connectionInfo );
	    if( $conn == false ) {
		    echo "Connection could not be established.<br />";
		    die( print_r( sqlsrv_errors(), true));
	    }
	    return $conn;
    }
}

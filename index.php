<?php
//**************************************************************************//
//************ API RESTFULL SLIM 2.6.3 PARA GESTION DE DISCOS **************//
//******************** Juan Sebastian Muñoz Reyes **************************//
//************************** Julio 11 2018 *********************************//
//**************************************************************************//





//--------------------------------------------------------------------------//
// librerias utilizadas en la aplicacion
//--------------------------------------------------------------------------//

require_once "vendor/autoload.php";

//--- Uso de firebase Jwt para generar token de autenticacion de usuario ---//

use \Firebase\JWT\JWT;

//--------------------------------------------------------------------------//


//--------------------------------------------------------------------------//
// Instancia del objeto Slim para ejecucion de la aplicacion
$app = new \Slim\Slim();
// Intancia del objeto db para nueva conexion con la base de datos 
$db = new mysqli('localhost','root','','musica_store');
// Charset para uso de caracteres especiales dentro de la base de datos
mysqli_set_charset($db,"utf8");
//--------------------------------------------------------------------------//


//--------------------------------------------------------------------------//
// Funcion initCors que inicializa y configura las cabeceras 
//--------------------------------------------------------------------------//
function initCors() {

    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers son recibidas durante solicitudes OPTION 
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // Cabecera para acceso a metodos GET, PUT, POST, DELETE, OPTIONS etc
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }

}
//---------------------------- End Method ----------------------------------//


//--------------------------------------------------------------------------//
// Llamada del metodo initCors declarado anteriormente 
	initCors();
//---------------------------- End Method ----------------------------------//




//--------------------------------------------------------------------------//
// Metodo encargado de obtener el token de autenticacion por medio de las 
// cabeceras 
//--------------------------------------------------------------------------//
function getToken(){
	// Almacenar las cabeceras 
	$headers = apache_request_headers();
	//  Condicion para verificar si existe la cabecera de autenticacion
	if(isset($headers["Authorization"])){

		$token = $headers["Authorization"];
		// Conseguir el token de la cabecera de autenticacion  
		$token = str_replace('Bearer ', '', $token);
		return $token;
	}
	return false;
}
//---------------------------- End Method ----------------------------------//





//--------------------------------------------------------------------------//
// Metodo encargado de validar el token y consultarlo dentro de los usuarios
// registrados en la base de datos
//--------------------------------------------------------------------------//
function validateToken($token, $db){
	// consulta de token dentro de la tabla usuarios
	$sql ="select * FROM usuarios where token_auth_usuario = '".$token."'";
	// resultado de la consulta
	$query = $db->query($sql);

	// condicion para verificar si la consulta fue exitosa y el token encontrado
	if($query){
		return true;
	}else{
		return false;
	}

}
//----------------------------- End Method ---------------------------------//





//**************************************************************************//
//*********************** SERVICIOS PARA EL USUARIO ************************//
//**************************************************************************//




//--------------------------------------------------------------------------//
// LOGIN O INICIO SESION DEL USUARIO
//--------------------------------------------------------------------------//
$app->post('/inicio-sesion', function() use($db, $app){
	// Obtener parametros de la solicitud y decodificarlos para su posterior uso 
	$json = $app->request->post('json');
	$data = json_decode($json, true);
	$is_login = false;
	// Consulta para obtener datos de la tabla Usuario de acuerdo al valor del email 
	$sql = "SELECT * FROM usuarios where email_usuario = "."'{$data["email"]}'";

	// 
	if($query = $db->query($sql)){

		$record = mysqli_fetch_assoc($query);
		if($record["contraseña_usuario"] == $data["contrasena"]){
			$is_login = true;
		}else{
			$is_login = false;
		}
		
	}

	// Validar si existe el registro y estado del login 
	if((isset($record)) && ($is_login == true)){
		// Arreglo con valores de exito en la ejecucion  
		$result = array(
			      	'status' => 'success',
			      	'code'	 => 200,
				  	'data' => $record
				  );
	}else{
		// Arreglo con valores de error en la ejecucion 
		$result = array(
			      	'status' => 'error',
			      	'code'	 => 404,
				  	'message' => 'Error al iniciar sesion'
				  );			
	}
	
	// Retornar valores codificados en formato JSON
	echo json_encode($result);
});
//----------------------------- End Method ---------------------------------//





//--------------------------------------------------------------------------//
// CREAR UN NUEVO USUARIO
//--------------------------------------------------------------------------//
$app->post('/create-usuario', function() use($db, $app){
	// Obtener parametros de la solicitud y decodificarlos para su posterior uso
	$json = $app->request->post('json');
	$data = json_decode($json, true);

	// validar la existencia de datos y en determinado caso asignar valores nulos
	if(!isset($data['direccion'])){
		$data['direccion'] = null;
	}
	if(!isset($data['telefono'])){
		$data['telefono'] = null;
	}
	if(!isset($data['fecha_nacimiento'])){
		$data['fecha_nacimiento'] = null;
	}

	// Creacion del token de autenticacion para el usuario que sera creado
	$jwt = JWT::encode($data, 'usuario');

	// Consulta de insercion de datos
	$query = "INSERT INTO usuarios VALUES(NULL,".
			 "'{$data['email']}',".
			 "'{$data['contrasena']}',".
			 "'{$data['nombre']}',".
			 "'{$data['apellido']}',".
			 "'{$data['identificacion']}',".
			 "'{$data['direccion']}',".
			 "'{$data['telefono']}',".
			 "'{$data['fecha_nacimiento']}',".
			 "'{$jwt}'".
			 ");";


	// Resultado de la consulta de insercion		 
	$insert = $db->query($query);

	// Almacenar valores que se retornaran dependiendo del exito
	// de la operacion

	$result = array(
		'status' => 'error',
		'code'	 => 404,
		'message' => 'El Usuario no se ha creado'
	);
	if($insert){
		$result = array(
			'status' => 'success',
			'code'	 => 200,
			'message' => 'Usuario creado correctamente'
		);
	}
	// Retornar Valores codificados en formato JSON
	echo json_encode($result);
});
//------------------------------ End Method --------------------------------//




//--------------------------------------------------------------------------//
// LISTAR INFORMACION DEL USUARIO
//--------------------------------------------------------------------------//
$app->get('/usuario/:id', function($id) use($db, $app){
	// Obtener token de autenticacion por medio del metodo get Token 
	$token = getToken();

	// Validar si el token existe 
	if($token){
		// Validar si el token esta asociado al usuario 
		if(validateToken($token,$db)){

			// Consulta Para obtener el registro de un usuario segun su id 
			$sql = 'SELECT * FROM usuarios where id_usuario = '.$id;
			// Ejecucion de la consulta y almacenamiento de su resultado
			$query = $db->query($sql);

			// Convertir objeto Mysqli result de la consulta a un arreglo asociativo
			// para asignar estos valores a un arreglo que sera retornado
			$registro_usuario = array();
			while ($row = $query->fetch_assoc()) {
				$registro_usuario[] = $row;
			}

			// arreglo que contiene los datos del exito de la operacion 
			// y los datos del usuario obtenidos
			$result = array(
					'status' => 'success',
					'code'	 => 200,
					'data' => $registro_usuario
				);

			// Retornar arreglo de datos en formato JSON
			echo json_encode($result);
		}else{
			// Respuesta en caso de existir usuario asociado al token
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}
			
	}else{
		// Respuesta en caso de no obtener el token de autenticacion
		echo json_encode(['Error'=>'Error al obtener token']);
	}

});
//------------------------------ End Method --------------------------------//




//--------------------------------------------------------------------------//
// ACTUALIZAR UN USUARIO
//--------------------------------------------------------------------------//
$app->put('/update-usuario/:id', function($id) use($db, $app){
	// Obtener el token de autenticacion por medio del metodo getToken
	$token = getToken();
	// Validar si el token fue obtenido
	if($token){
		// Validar si el token esta asociado al usuario 
		if(validateToken($token,$db)){
			// Obtener parametros de la solicitud y decodificarlos para su posterior uso
			$json = $app->request->put('json');
			$data = json_decode($json, true);
			
			// Consulta sql con sus respectivos valores de actualizacion
			$sql = "UPDATE usuarios SET ".
				   "nombre_usuario = '{$data["nombre_usuario"]}', ".
				   "apellido_usuario = '{$data["apellido_usuario"]}', ".
				   "email_usuario = '{$data["email_usuario"]}', ".
				   "contraseña_usuario = '{$data["contrasena_usuario"]}'".
				   " WHERE id_usuario = {$id}";

			// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta	   
			$query = $db->query($sql);

			// Asignacion de valores de acuerdo al exito o error de la operacion
			$result = array(
				'status' 	=> 'error',
				'code'		=> 404,
				'message' 	=> 'El usuario no se ha actualizado!!'
			);
			
			if($query){
				$result = array(
					'status' => 'success',
					'code'	 => 200,
					'message' => 'Usuario actualizado correctamente'
				);
			}	

			// Retornar arreglo de datos en formato JSON
			echo json_encode($result);
		}else{
			// Respuesta en caso de no estar asociado el token al usuario
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}
			
	}else{
		// Respuesta en caso de no obtener el token por la cabecera
		echo json_encode(['Error'=>'Error al obtener token']);
	}
	

});

//------------------------------ End Method --------------------------------//






//**************************************************************************//
//******************** SERVICIOS PARA LOS DISCOS ***************************//
//**************************************************************************//


//--------------------------------------------------------------------------//
// LISTAR TODOS LOS DISCOS
//--------------------------------------------------------------------------//
$app->get('/discos', function() use($db, $app){
	// Consulta para obtener datos de todos los discos registrados
	$sql = "SELECT generos.nombre_genero as 'genero_disco', discos.id_disco , discos.nombre_disco, discos.autor_disco, discos.album_disco, discos.fecha_lanzamiento_disco, discos.registro_usuario_disco FROM discos INNER JOIN generos ON discos.genero_disco = generos.id_genero ORDER BY id_disco DESC ";
	// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta
	$query = $db->query($sql);

	// Convertir objeto Mysqli result de la consulta a un arreglo asociativo
	// para asignar estos valores a un arreglo que sera retornado
	$registro_disco = array();
	while ($disco = $query->fetch_assoc()) {
		$registro_disco[] = $disco;
	}

	// arreglo que contiene los datos del exito de la operacion 
	// y los datos de los discos obtenidos
	$result = array(
			'status' => 'success',
			'code'	 => 200,
			'data' => $registro_disco
		);
	// Retornar arreglo de datos en formato JSON
	echo json_encode($result);
});
//------------------------------ End Method --------------------------------//





//--------------------------------------------------------------------------//
// LISTAR TODOS LOS GENEROS
//--------------------------------------------------------------------------//
$app->get('/generos', function() use($db, $app){
	// Obterer token de autenticacion por medio del metodo getToken
	$token = getToken();

	// Validar si este token fue recibido por medio de las cabeceras
	if($token){
		// Validar si este token esta asociado al usuario 
		if(validateToken($token,$db)){
			
			// Consulta para obtener todos los generos musicales registrados
			$sql = 'SELECT * FROM generos ORDER BY id_genero ASC';

			// Ejecucion de la consulta y almacenamiento de datos obtenidos
			$query = $db->query($sql);

			// Convertir objeto Mysqli result de la consulta a un arreglo asociativo
			// para asignar estos valores a un arreglo que sera retornado
			$genero_disco = array();
			while ($row = $query->fetch_assoc()) {
				$genero_disco[] = $row;
			}
			// Arreglo que contiene los datos del exito de la operacion 
			// y los datos de los generos obtenidos
			$result = array(
					'status' => 'success',
					'code'	 => 200,
					'data' => $genero_disco
				);
			// Retornar arreglo de datos en formato JSON
			echo json_encode($result);
		}else{
			// Respuesta en caso de que el usuario no este asociado al token 
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}
			
	}else{
		// Respuesta en caso de no obetener el token por medio de la cabecera
		echo json_encode(['Error'=>'Error al obtener token']);
	}
	
});

//------------------------------ End Method --------------------------------//





//--------------------------------------------------------------------------//
// OBTENER UN SOLO DISCO
//--------------------------------------------------------------------------//

$app->get('/disco/:id', function($id) use($db, $app){

	// Consulta del disco de acuerdo a su id 
	$sql = "SELECT generos.nombre_genero as 'genero', discos.id_disco as 'id', discos.nombre_disco as 'nombre', discos.autor_disco as 'autor', discos.album_disco as 'album', discos.fecha_lanzamiento_disco as 'fecha_lanzamiento' FROM discos INNER JOIN generos ON discos.genero_disco = generos.id_genero WHERe discos.id_disco = ".$id;

	// Ejecucion de la consulta y almacenamiento de datos obtenidos
	$query = $db->query($sql);

	// Almacenar valores que se retornaran dependiendo del exito
	// de la operacion
	$result = array(
		'status' 	=> 'error',
		'code'		=> 404,
		'message' 	=> 'Disco no disponible'
	);

	if($query->num_rows == 1){
		$disco = $query->fetch_assoc();

		$result = array(
			'status' 	=> 'success',
			'code'		=> 200,
			'data' 	=> $disco
		);
	}
	// Retornar arreglo de datos en formato JSON
	echo json_encode($result);
});

//------------------------------ End Method --------------------------------//





//--------------------------------------------------------------------------//
// BUSCAR DISCOS POR VALOR(NOMBRE, AUTOR, ALBUM, GENERO)
//--------------------------------------------------------------------------//
$app->post('/search-disco', function() use($db, $app){
	// Obtener parametros de la solicitud y decodificarlos para su posterior uso
	$json = $app->request->post('json');
	$data = json_decode($json, true);
	
	// Consulta para obtener registros de discos asociados al valor dado(sea nombre, autor, album, genero) 
	$sql = "SELECT generos.nombre_genero as 'genero_disco', discos.id_disco , discos.nombre_disco, discos.autor_disco, discos.album_disco, discos.fecha_lanzamiento_disco, discos.registro_usuario_disco FROM discos INNER JOIN generos ON discos.genero_disco = generos.id_genero WHERE discos.nombre_disco LIKE "."'%{$data}%'"." OR discos.album_disco LIKE "."'%{$data}%'"." OR discos.autor_disco LIKE "."'%{$data}%'"."OR generos.nombre_genero LIKE "."'%{$data}%'"."GROUP BY discos.id_disco ORDER BY discos.id_disco DESC";

	// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta
	$query = $db->query($sql);

	// Convertir objeto Mysqli result de la consulta a un arreglo asociativo
	// para asignar estos valores a un arreglo que sera retornado
	$registro_disco = array();
	while ($row = $query->fetch_assoc()) {
		$registro_disco[] = $row;
	}

	// Almacenar datos de exito de la operacion y registro obtenido 
	$result = array(
			'status' => 'success',
			'code'	 => 200,
			'data' => $registro_disco
		);

	// Retornar arreglo de datos en formato JSON
	echo json_encode($result);
});

//------------------------------ End Method --------------------------------//




//--------------------------------------------------------------------------//
// CREAR UN NUEVO DISCO
//--------------------------------------------------------------------------//
$app->post('/create-disco', function() use($db, $app){
	// Obtener el token de autenticacion por medio del metodo getToken
	$token = getToken();

	// Validar si el token fue obtenido
	if($token){
		
		// Validar si el token esta asociado al usuario  
		if(validateToken($token,$db)){
			
			// Obtener parametros de la solicitud y decodificarlos para su posterior uso
			$json = $app->request->post('json');
			$data = json_decode($json, true);
			
			// Asignacion de valor null en caso de no existir un valor asignado	
			if(!isset($data['fecha_lanzamiento'])){
				$data['fecha_lanzamiento'] = null;
			}
			
			// Consulta sql con sus respectivos valores de insercion
			$query = "INSERT INTO discos VALUES(NULL,".
					 "'{$data['nombre']}',".
					 "'{$data['autor']}',".
					 "'{$data['genero']}',".
					 "'{$data['album']}',".
					 "'{$data['fecha_lanzamiento']}',".
					 "'{$data['registro_usuario']}'".
					 ");";
			// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta
			$insert = $db->query($query);

			// Asignacion de valores de acuerdo al exito o error de la operacion 	
			$result = array(
				'status' => 'error',
				'code'	 => 404,
				'message' => 'El Disco no se ha creado'
			);
			if($insert){
				$result = array(
					'status' => 'success',
					'code'	 => 200,
					'message' => 'Disco creado correctamente'
				);
			}
			// Retornar arreglo de datos en formato JSON
			echo json_encode($result);
		}else{
			// Respuesta en caso de no estar asociado el token al usuario
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}
			
	}else{
		// Respuesta en caso de no obtener el token por la cabecera
		echo json_encode(['Error'=>'Error al obtener token']);
	}

});

//------------------------------ End Method --------------------------------//




//--------------------------------------------------------------------------//
// ACTUALIZAR UN DISCO
//--------------------------------------------------------------------------//
$app->put('/update-disco/:id', function($id) use($db, $app){

	// Obtener el token de autenticacion por medio del metodo getToken
	$token = getToken();

	// Validar si el token fue obtenido
	if($token){

		// Validar si el token esta asociado al usuario 
		if(validateToken($token,$db)){

			// Obtener parametros de la solicitud y decodificarlos para su posterior uso
			$json = $app->request->put('json');
			$data = json_decode($json, true);
			
			// Consulta sql con sus respectivos valores de actualizacion
			$sql = "UPDATE discos SET ".
				   "nombre_disco = '{$data["nombre"]}', ".
				   "autor_disco = '{$data["autor"]}', ".
				   "genero_disco = '{$data["genero"]}', ".
				   "album_disco = '{$data["album"]}', ".
				   "fecha_lanzamiento_disco = '{$data["fecha_lanzamiento"]}', ".
				   "registro_usuario_disco = '{$data["registro_usuario"]}'".
				   " WHERE id_disco = {$id}";

			// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta	   
			$query = $db->query($sql);

			// Asignacion de valores de acuerdo al exito o error de la operacion
			$result = array(
				'status' 	=> 'error',
				'code'		=> 404,
				'message' 	=> 'El disco no se ha actualizado!!'
			);
			
			if($query){
				$result = array(
					'status' => 'success',
					'code'	 => 200,
					'message' => 'Disco creado correctamente'
				);
			}	
			// Retornar arreglo de datos en formato JSON
			echo json_encode($result);
		}else{
			// Respuesta en caso de no estar asociado el token al usuario
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}

	}else{
		// Respuesta en caso de no obtener el token por la cabecera
		echo json_encode(['Error'=>'Error al obtener el token']);
	}	
	

});

//------------------------------- End Method -------------------------------//





//--------------------------------------------------------------------------//
// ELIMINAR UN DISCO
//--------------------------------------------------------------------------//
$app->delete('/delete-disco/:id', function($id) use($db, $app){
	// Obtener el token por medio del metodo getToken
	$token = getToken();
	// Validar si el token fue obtenido de las cabeceras
	if($token){
		// Validar si el token esta asociado al usuario  
		if(validateToken($token,$db)){
			// Consulta para borrar el disco asociado a un id
			$sql = 'DELETE FROM discos WHERE id_disco = '.$id;
			// Ejecucion de la consulta y almacenamiento de los datos obtenidos por esta
			$query = $db->query($sql);

			// Asignacion de valores de acuerdo al exito o error de la operacion 
			if($query){
				$result = array(
					'status' 	=> 'success',
					'code'		=> 200,
					'message' 	=> 'El disco se ha eliminado correctamente!!'
				);
			}else{
				$result = array(
					'status' 	=> 'error',
					'code'		=> 404,
					'message' 	=> 'El disco no se ha eliminado!!'
				);
			}
			// Retornar arreglo de datos en formato JSON	
			echo json_encode($result);		
		}else{
			// Respuesta en caso de no estar asociado el token al usuario
			echo json_encode(['Error'=>'No Existe Usuario asociado a este token']);
		}
			
	}else{
		// Respuesta en caso de no obtener el token por la cabecera
		echo json_encode(['Error'=>'Error al obtener token']);
	}

	
});

//------------------------------- End Method -------------------------------//







//--------------------------------------------------------------------------//
// EJECUTAR APLICACION
$app->run();
//--------------------------------------------------------------------------//
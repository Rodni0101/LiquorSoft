<?php
$conexion = new mysqli("localhost", "root", "1048442903", "");

if ($conexion->connect_error) {
    die($conexion->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = $_POST["nombre"];
    $telefono = $_POST["telefono"];
    $correo = $_POST["correo"];

    $consulta = "INSERT INTO // (Nombre, Telefono, Correo)
            VALUES('$nombre', '$telefono', '$correo')";


    $preparacion = $conexion->prepare($consulta);

    $preparacion->bind_param("sss", $nombre, $telefono, $correo);


    if ($preparacion->execute()) {
        echo "<script>
            alert('Usuario guardado correctamente.');
            window.location.href = 'Formulario.php';
        </script>";
    } else {
        echo "<script>
            alert('No se pudo guardar el usuario: " . addslashes($preparacion->error) . "');
            window.history.back();
        </script>";
    }

    $preparacion->close();

    $conexion->close();
    
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LiquorSoft</title>
    <link rel="stylesheet" href="./app.css">
</head>
<header class="formulario">
    <h1>Register</h1>
</header>

<body>
    <div class="register">
        <strong>Register</strong>
        <form action="../../../backend/conexion.php" method="post">
            <label for="title">Nombre
                <input type="text" placeholder="Nombre Completo" required name="nombre" />
            </label>

            <label for="title">Telefono
                <input type="number" placeholder="Numero Telefonico" required name="telefono" />
            </label>

            <label for="title">Correo Electronico
                <input type="email" placeholder="Correo Electronico" required name="correo" />
            </label>
            <button type="submit">Registrarse</button>
        </form>
    </div>
</body>

</html>
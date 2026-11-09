<?php

$conn = new PDO(
    "mysql:host=127.0.0.1;port=3306;dbname=princek2;charset=utf8mb4",
    "root",
    ""
);

$conn->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

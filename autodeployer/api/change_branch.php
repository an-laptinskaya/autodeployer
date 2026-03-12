<?php

$inputJSON = file_get_contents('php://input');

echo json_encode(['status' => 'success', 'body' => $inputJSON]);
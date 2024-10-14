<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Connect to the database
$mysqli = new mysqli('localhost', 'root', '', 'document_signing_db');

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if a file was uploaded
if (isset($_FILES['file'])) {
    // Specify the upload directory
    $uploadDir = 'uploads/';
    
    // Ensure the uploads directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Create the directory if it doesn't exist
    }

    // Generate a unique name for the file
    $fileName = uniqid() . '_' . basename($_FILES['file']['name']);
    $filePath = $uploadDir . $fileName;

    // Move the uploaded file to the uploads directory
    if (move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
        // Get the name from the form (if provided, else default to 'No Name')
        $name = isset($_POST['name']) ? $_POST['name'] : 'No Name';

        // Prepare the SQL statement to insert data (name, fileNotSigned, link, sendButton)
        $stmt = $mysqli->prepare("INSERT INTO Signing_Document (name, fileNotSigned, sendButton) VALUES (?, ?, ?)");

        // Initialize sendButton status
        $sendButton = 0;

        // Bind the parameters and execute the query
        $stmt->bind_param("ssi", $name, $filePath, $sendButton);

        if ($stmt->execute()) {
            // After inserting, retrieve the last inserted document ID for the link
            $documentId = $stmt->insert_id;

            // Generate the unique link with the correct document ID
            $uniqueLink = "http://localhost/pdf/view_document.php?id=" . $documentId;

            // Update the table with the unique link
            $updateStmt = $mysqli->prepare("UPDATE Signing_Document SET link = ? WHERE id = ?");
            $updateStmt->bind_param("si", $uniqueLink, $documentId);
            $updateStmt->execute();
            $updateStmt->close();

            // Send success response as JSON
            echo json_encode(["status" => "success", "message" => "PDF uploaded and saved successfully", "link" => $uniqueLink]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error saving to database: " . $stmt->error]);
        }

        // Close the statement and connection
        $stmt->close();
        $mysqli->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Error moving uploaded file"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No file uploaded"]);
}
?>

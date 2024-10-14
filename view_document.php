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

// Get the document ID from the query string
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the document details from the database
$stmt = $mysqli->prepare("SELECT id, name, fileNotSigned, link FROM Signing_Document WHERE id = ?");
$stmt->bind_param("i", $documentId);
$stmt->execute();
$stmt->bind_result($docId, $name, $fileNotSigned, $link);
$stmt->fetch();
$stmt->close();

// Ensure the document exists
if (!$name) {
    die("Document not found.");
}

echo "<script>const documentId = {$docId};</script>";

// Include Composer's autoloader if you're using Composer
require 'vendor/autoload.php';

// Use the FPDI namespace
use setasign\Fpdi\Fpdi;

// Handle POST request for saving the signed PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Decode the Base64-encoded signature image
        $signatureImage = $_POST['signaturePreview'];

        if (empty($signatureImage)) {
            throw new Exception('Signature data not received.');
        }

        // Remove "data:image/png;base64," from the signature image data
        $signatureImage = str_replace('data:image/png;base64,', '', $signatureImage);
        $signatureImage = base64_decode($signatureImage);

        // Save the signature image temporarily
        $signaturePath = 'temp_signature.png';
        if (file_put_contents($signaturePath, $signatureImage) === false) {
            throw new Exception('Failed to save signature image.');
        }

        // Fetch document details again to ensure the latest data
        $stmt = $mysqli->prepare("SELECT id, name, fileNotSigned, link FROM Signing_Document WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database prepare statement failed: ' . $mysqli->error);
        }
        $stmt->bind_param("i", $documentId);
        $stmt->execute();
        $stmt->bind_result($docId, $name, $fileNotSigned, $link);
        $stmt->fetch();
        $stmt->close();

        // Path to the original PDF
        $originalPdf = $fileNotSigned;

        // Ensure the original PDF exists
        if (!file_exists($originalPdf)) {
            throw new Exception('Original PDF not found.');
        }

        // Directory to save the signed PDFs
        $signedPdfDir = 'uploads_signed/';
        if (!file_exists($signedPdfDir) && !mkdir($signedPdfDir, 0777, true)) {
            throw new Exception('Failed to create directory for signed PDFs.');
        }

        // Define the path for the signed PDF
        $signedPdfPath = $signedPdfDir . 'signed_document_' . $documentId . '.pdf';

        // Initialize FPDI
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($originalPdf);

        // Import each page and add the signature to the last page
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            // Add a page with the same orientation and size as the template
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            // Add the signature to the last page
            if ($pageNo == $pageCount) {
                // Adjust the X and Y coordinates as needed
                $x = 10; // Horizontal position
                $y = 250; // Vertical position
                $width = 80; // Width of the signature image
                $height = 40; // Height of the signature image

                // Add the signature image to the PDF
                $pdf->Image($signaturePath, $x, $y, $width, $height);
            }
        }

        // Save the signed PDF
        $pdf->Output('F', $signedPdfPath);

        // Remove the temporary signature image
        unlink($signaturePath);

        // Optionally, update the database with the path to the signed PDF
        $stmt = $mysqli->prepare("UPDATE Signing_Document SET signFiled = ?, sendButton = 1 WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database prepare statement failed: ' . $mysqli->error);
        }
        $stmt->bind_param("si", $signedPdfPath, $documentId);
        $stmt->execute();
        $stmt->close();

        // Force file download by sending appropriate headers
        if (file_exists($signedPdfPath)) {
            // Set headers to force download
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf'); // Specify correct content type
            header('Content-Disposition: attachment; filename="' . basename($signedPdfPath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($signedPdfPath));

            // Clear output buffer
            if (ob_get_length()) ob_end_clean();
            flush();

            // Read and serve the file for download
            readfile($signedPdfPath);

            exit;
        } else {
            echo "File not found!";
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}



?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Document</title>
        <style>
            /* Basic styling for the document view */
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                display: flex;
                flex-direction: column;
                align-items: center; /* Center items horizontally */
                justify-content: center; /* Center items vertically */
            }
            #head {
                font-family: Arial, sans-serif;
                margin: 20px;
                display: flex;
                flex-direction: column;
                align-items: center; /* Center items horizontally */
                justify-content: center; /* Center items vertically */
                width: 100%;
            }
            #signature-pad {
                border: 1px solid #ccc;
                width: 100%;
                height: 200px;
                position: relative;
            }
            canvas {
                border: 1px solid #000;
                width: 100%; /* Make canvas responsive */
                height: 100%; /* Make canvas responsive */
            }
            #modal {
                display: none; /* Hidden by default */
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.7); /* Dark background with transparency */
                display: flex; /* Use flexbox to center modal */
                align-items: center; /* Center vertically */
                justify-content: center; /* Center horizontally */
            }
            #modal-content {
                background-color: #fff;
                border-radius: 8px; /* Rounded corners */
                padding: 20px;
                width: 90%; /* Responsive width */
                max-width: 600px; /* Max width for larger screens */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Shadow effect */
                position: relative; /* Position relative for close button */
            }
            #close-modal {
                cursor: pointer;
                position: absolute;
                top: 10px;
                right: 15px; /* Position close button */
                font-size: 24px;
            }
            /* Add Sign button styling */
            #add-sign-btn {
                font-size: 20px; /* Larger text */
                padding: 15px 30px; /* More padding for a bigger button */
                border-radius: 5px; /* Rounded corners */
                background-color: #007BFF; /* Blue background */
                color: white; /* White text */
                border: none; /* No border */
                cursor: pointer; /* Pointer cursor on hover */
                transition: background-color 0.3s; /* Smooth transition */
            }
            #add-sign-btn:hover {
                background-color: #0056b3; /* Darker blue on hover */
            }
            #buttons {
                display: flex;
                justify-content: space-between; /* Space buttons evenly */
                margin-top: 10px; /* Margin above buttons */
            }
            #confirm-signature, #clear-signature {
                padding: 10px 15px; /* Padding for buttons */
                border-radius: 5px; /* Rounded corners */
                border: none; /* No border */
                cursor: pointer; /* Pointer cursor on hover */
            }
            #confirm-signature {
                background-color: #28a745; /* Green for confirm */
                color: white; /* White text */
            }
            #clear-signature {
                background-color: #dc3545; /* Red for clear */
                color: white; /* White text */
            }
            #confirm-signature:hover {
                background-color: #218838; /* Darker green on hover */
            }
            #clear-signature:hover {
                background-color: #c82333; /* Darker red on hover */
            }
            #thank-you-message {
                display: none; /* Keep this for initial hidden state */
                background-color: #e7f3ff; /* Light blue background color */
                border: 2px solid #007bff; /* Blue border */
                border-radius: 8px; /* Rounded corners */
                padding: 20px; /* Spacing inside the div */
                margin: 20px; /* Space around the div */
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow */
                text-align: center; /* Center-align text */
                animation: fadeIn 0.5s; /* Fade-in animation */
            }

            #thank-you-message h2 {
                color: #007bff; /* Blue color for the heading */
                margin-bottom: 10px; /* Space below the heading */
            }

            #thank-you-message p {
                color: #333; /* Dark gray color for the paragraph */
            }
        </style>

    </head>
    <body>
        <div id="head">
            <h1>Document: <?php echo htmlspecialchars($name); ?></h1>
            <p>Download the document: <a href="<?php echo htmlspecialchars($link); ?>">Download PDF</a></p>

            <!-- Display the fileNotSigned document in an iframe -->
            <iframe src="<?php echo htmlspecialchars($fileNotSigned); ?>" width="100%" height="600px"></iframe>
        </div>
        <br> 

        <!-- "Add Sign" button -->
        <button id="add-sign-btn">Add Sign</button>

        <!-- Modal for signature pad -->
        <div id="modal" style="display:none;">
            <div id="modal-content">
                <span id="close-modal">&times;</span>
                <h2>Add Signature</h2>
                <div id="signature-pad">
                    <canvas id="canvas" width="600" height="200"></canvas>
                </div>
                <div id="buttons">
                    <button id="confirm-signature" style="display:none;">Confirm Signature</button>
                    <button id="clear-signature" style="display:none;">Clear Signature</button>
                </div>
            </div>
        </div>

        <div id="signatureDiv" style="display:none;">
            <h3>Your Signature:</h3>
            <img id="signaturePreview" alt="Signature" style="border: 1px solid black; max-width: 100%; height: auto;"> <!--  Initially hidden -->
        </div>

        <div id="thank-you-message" style="display: none;">
            <h2>Thank You!</h2>
            <p>Your signature has been saved successfully.</p>
        </div>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

        <script>
            const modal = document.getElementById("modal");
            const addSignBtn = document.getElementById("add-sign-btn");
            const closeModal = document.getElementById("close-modal");
            const confirmSignatureBtn = document.getElementById("confirm-signature");
            const clearSignatureBtn = document.getElementById("clear-signature");
            const canvas = document.getElementById("canvas");
            const ctx = canvas.getContext("2d");
            const thankMessage = document.getElementById("thank-you-message");
            const head = document.getElementById("head");

            // Function to open modal
            function openModal() {
                modal.style.display = "flex"; // Show modal
                confirmSignatureBtn.style.display = "block"; // Show confirm button
                clearSignatureBtn.style.display = "block"; // Show clear button
                ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear canvas when opening the modal
            }

            // Function to close modal
            function closeModalFunction() {
                modal.style.display = "none"; // Hide modal
                ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear canvas
                confirmSignatureBtn.style.display = "none"; // Hide confirm button
                clearSignatureBtn.style.display = "none"; // Hide clear button
            }

            // Event listeners
            addSignBtn.onclick = openModal;
            closeModal.onclick = closeModalFunction;

            // Set up canvas drawing
            let drawing = false;

            canvas.addEventListener("mousedown", function(event) {
                drawing = true;
                ctx.beginPath();
                ctx.moveTo(event.offsetX, event.offsetY);
            });

            canvas.addEventListener("mousemove", function(event) {
                if (drawing) {
                    ctx.lineTo(event.offsetX, event.offsetY);
                    ctx.stroke();
                }
            });

            canvas.addEventListener("mouseup", function() {
                drawing = false;
                ctx.closePath();
            });

            // Clear Signature button click event
            clearSignatureBtn.onclick = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear the canvas
            };




            const messageDiv = document.getElementById("message");

            // Function to handle Confirm Signature
            // JavaScript to handle the download after signature
            confirmSignatureBtn.onclick = function() {
                const signatureImage = canvas.toDataURL('image/png'); // Get signature as base64

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `signaturePreview=${encodeURIComponent(signatureImage)}`
                }).then(response => {
                    if (response.ok) {
                        // Trigger the download if response is successful
                        response.blob().then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            a.download = 'signed_document.pdf';
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                        });
                    } else {
                        console.error('Download failed');
                    }
                });
                showThankMessage();
            };

            function showThankMessage() {
                console.log(signatureDiv); // Debugging check
                head.style.display = "none";
                addSignBtn.style.display = "none";
                modal.style.display = "none";
                
                if (signatureDiv) {
                    signatureDiv.style.display = "none"; // Ensure it's only hidden if it exists
                }

                thankMessage.style.display = "block"; // Show thank you message
            }


            let signatureDiv; // Declare signatureDiv globally to access it later

            function saveSignature() {
                const signatureImage = canvas.toDataURL('image/png');

                // Check if signatureDiv already exists
                if (!signatureDiv) {
                    signatureDiv = document.createElement('div');
                    signatureDiv.id = 'signatureDiv';
                    signatureDiv.classList.add('draggable-signature');
                    signatureDiv.style.position = 'absolute';
                    signatureDiv.style.display = 'block'; // Make sure it's visible
                    document.body.appendChild(signatureDiv); // Add to the body or appropriate container
                }

                // Update or create the signature image element
                let signaturePreview = document.getElementById('signaturePreview');
                if (!signaturePreview) {
                    signaturePreview = new Image(); // Create a new image if it doesn't exist
                    signaturePreview.id = 'signaturePreview'; // Use a fixed ID
                    signaturePreview.style.maxWidth = '100px'; // Example size
                    signaturePreview.style.maxHeight = '50px';
                    signatureDiv.appendChild(signaturePreview); // Add the image to the signatureDiv
                }

                // Update the src to the new signature image
                signaturePreview.src = signatureImage; // Update the image with the new signature
                
            }

        </script>

    </body>
</html>

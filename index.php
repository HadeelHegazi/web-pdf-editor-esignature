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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 5px;
            display: flex;
            margin: 0;
            height: 100vh; /* Full height of the viewport */
            width: 100%; /* Full width */
            background-color: #d0e0f0; 
        }

        .left-div {
            width: 60%; /* Adjust width as needed */
            padding: 5px; /* Inner spacing */
        }

        .right-div {
            background-color: #f0f0f0; /* Light blue */
            width: 45%; /* Adjust width as needed */
            padding: 5px; /* Inner spacing */
            height: 100%;
        }

        .signature-container {
            border: 1px solid #000;
            margin: 20px auto;
            width: 100%;
            max-width: 400px;
            height: 100px;
        }

        canvas {
            width: 100%;
            height: auto;
        }

        button {
            /* margin-top: 10px; */
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }

        #fileDisplay {
            border: 1px solid #ddd;
            position: relative;
            height: auto;
            overflow: hidden;
            background: #f9f9f9; /* Background color for visibility */
        }

        #uploadArea {
            border: 2px dashed #bbb;
            padding: 5px;
            margin: 20px 0;
        }

        .draggable {
            cursor: move;
            padding: 5px;
            margin: 5px;
            border: 1px dashed #000;
            width: fit-content;
            display: inline-block;
            background-color: #e0e0e0; /* Added background color for visibility */
        }

        .draggable-image {
            width: 200px;
            height: 200px;
        }

        .dropped {
            position: absolute;
            padding: 5px;
            cursor: move;
            resize: both; 
            overflow: auto; /* Allow overflow when resizing */
        }

        .remove-btn {
            cursor: pointer;
            color: red;
            font-size: 12px;
            display: inline-block;
            margin-left: 10px;
        }

        .pagination {
            margin-top: 20px;
        }

        .resize-handle {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background-color: gray;
            cursor: se-resize;
            display: none; 
        }

        .dropped .remove-btn,
        .dropped .resize-handle {
            display: none; /* Hidden by default */
        }

        .dropped:hover .remove-btn,
        .dropped:hover .resize-handle {
            display: inline-block; /* Show on hover */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
        }
        /* Adjusting column width */
        th:nth-child(1), td:nth-child(1) {
            width: 15%; /* Set width for first column (Field Number) */
        }

        th:nth-child(2), td:nth-child(2) {
            width: 40%; /* Set width for second column (Fill-in Form Element) */
        }

        th:nth-child(3), td:nth-child(3) {
            width: 45%; /* Set width for third column (Draggable Element) */
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
            color: #333;
        }

        tr {
            height: 10px; /* Row height */
        }

        /* Zebra stripe for the table rows */
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:nth-child(odd) {
            background-color: #e6e6e6;
        }

        /* Hover effect for the table rows */
        tr:hover {
            background-color: #d1d1d1;
        }

        /* Style for draggable and input elements */
        .draggable, .inputField {
            font-size: 12px;
            margin: 0;
        }

        /* Make the table scrollable with a fixed header */
        .scrollable-table {
            border: 1px solid #ccc;
            width: 100%;
            max-height: 200px; /* Adjust height based on needs */
            overflow-y: auto;
        }

        thead {
            display: table;
            width: 100%;
            table-layout: fixed; /* Ensures fixed column width */
        }

        tbody {
            display: block;
            width: 100%;
            max-height: 800px; /* Set the scrollable body height */
            overflow-y: scroll;
        }

        tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        /* Responsive styling */
        @media (max-width: 600px) {
            th, td {
                padding: 5px;
                font-size: 10px;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>
<body>
    <div class="left-div">
        <input type="file" id="fileInput" accept="application/pdf,image/*" required>
        <div id="uploadArea">
            <div id="fileDisplay" ondragover="allowDrop(event)" ondrop="drop(event)">
                Drop your PDF or image here
            </div>
        </div>
    </div>

    <div class="right-div">
        <form id="dataForm">
            <div id="formFields">
                <div class="pagination">
                    <button id="prevPage" onclick="changePage(-1)">Previous</button>
                    <button id="nextPage" onclick="changePage(1)">Next</button>
                </div>
                <h3>Drag Fields Into the Document</h3>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Field Number</th>
                            <th>Fill-in Form Element</th>
                            <th>Draggable Element</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>
                                <input type="text" name="1" id="1" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="1" ondragstart="drag(event)">
                                    1 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>
                                <input type="text" name="2" id="2" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="2" ondragstart="drag(event)">
                                    2 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>
                                <input type="text" name="3" id="3" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="3" ondragstart="drag(event)">
                                    3 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>
                                <input type="text" name="4" id="4" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="4" ondragstart="drag(event)">
                                    4 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>
                                <input type="text" name="5" id="5" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="5" ondragstart="drag(event)">
                                    5 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td>
                                <input type="text" name="6" id="6" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="6" ondragstart="drag(event)">
                                    6 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>
                                <input type="text" name="7" id="7" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="7" ondragstart="drag(event)">
                                    7 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td>
                                <input type="text" name="8" id="8" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="8" ondragstart="drag(event)">
                                    8 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>
                                <input type="text" name="9" id="9" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="9" ondragstart="drag(event)">
                                    9 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>10</td>
                            <td>
                                <input type="text" name="10" id="10" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="10" ondragstart="drag(event)">
                                    10 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>11</td>
                            <td>
                                <input type="text" name="11" id="11" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="11" ondragstart="drag(event)">
                                    11 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>12</td>
                            <td>
                                <input type="text" name="12" id="12" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="12" ondragstart="drag(event)">
                                    12 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>13</td>
                            <td>
                                <input type="text" name="13" id="13" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="13" ondragstart="drag(event)">
                                    13 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>14</td>
                            <td>
                                <input type="text" name="14" id="14" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="14" ondragstart="drag(event)">
                                    14 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>15</td>
                            <td>
                                <input type="text" name="15" id="15" class="inputField" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" data-target="15" ondragstart="drag(event)">
                                    15 <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Image 1</td>
                            <td>
                                <label>Image1: </label>
                                <input type="file" name="image1" id="image1Input" accept="image/*" onchange="previewImage(event, 'image1')" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="image1" data-target="image1" ondragstart="drag(event)">
                                    <img src="" alt="Image 1" class="draggable-image" id="image1Preview">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Image 2</td>
                            <td>
                                <label>Image2: </label>
                                <input type="file" name="image2" id="image2Input" accept="image/*" onchange="previewImage(event, 'image2')" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="image2" data-target="image2" ondragstart="drag(event)">
                                    <img src="" alt="Image 2" class="draggable-image" id="image2Preview">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Image 3</td>
                            <td>
                                <label>Image3: </label>
                                <input type="file" name="image3" id="image3Input" accept="image/*" onchange="previewImage(event, 'image3')" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="image3" data-target="image3" ondragstart="drag(event)">
                                    <img src="" alt="Image 3" class="draggable-image" id="image3Preview">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Image 4</td>
                            <td>
                                <label>Image4: </label>
                                <input type="file" name="image4" id="image4Input" accept="image/*" onchange="previewImage(event, 'image4')" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="image4" data-target="image4" ondragstart="drag(event)">
                                    <img src="" alt="Image 4" class="draggable-image" id="image4Preview">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Image 5</td>
                            <td>
                                <label>Image5: </label>
                                <input type="file" name="image5" id="image5Input" accept="image/*" onchange="previewImage(event, 'image5')" required>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="image5" data-target="image5" ondragstart="drag(event)">
                                    <img src="" alt="Image 5" class="draggable-image" id="image5Preview">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Signature</td>
                            <td>
                                <div id="signaturePadContainer" style="display:none;">
                                    <label>Signature: </label>
                                    <canvas id="signatureCanvas" width="200" height="100" style="border:1px solid #000;"></canvas><br>
                                    <button id="saveSignatureBtn" type="button" onclick="saveSignature()">Save Signature</button>
                                    <button id="clearSignatureBtn" type="button" onclick="clearSignature()" style="display:none;">Clear Signature</button>
                                </div>
                                <button id="addSignatureBtn" type="button" onclick="openSignaturePad()">Add Signature</button>
                                <br>
                            </td>
                            <td>
                                <div class="draggable" draggable="true" id="signatureDiv" data-target="signature" ondragstart="drag(event)">
                                    <img src="" alt="Signature" id="signaturePreview" class="draggable-image">
                                    <span class="remove-btn" onclick="removeField2(this)">X</span>
                                    <div class="resize-handle"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </br>

                <button id="clear-btn" type="button">Clear</button>
                <button type="submit" value="Fill and Download">Fill and Download</button>

                <!-- <div id="previewContainer" style="display:none;"></div> -->
                <!-- <button id="downloadBtn" style="display:none;">Download PDF</button> -->
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>

        let pdfDoc = null;
        const fileInput = document.getElementById('fileInput');
        const fileDisplay = document.getElementById('fileDisplay');
        let currentPage = 1;
        let totalPages = 0;

        // Allow drop function
        function allowDrop(ev) {
            ev.preventDefault();
        }

        // Global counter variable to assign unique IDs
        let globalCounter = 0;

        // Drag function
        function drag(ev) {
            const targetData = ev.target.getAttribute('data-target') || ev.target.parentElement.getAttribute('data-target');
            ev.dataTransfer.setData("text", targetData);

            const elementId = ev.target.getAttribute('data-id') || '';
            ev.dataTransfer.setData("element-id", elementId);

            // Debugging: Log the dragged element's target data and ID
            console.log(`Dragging: Target Data - ${targetData}, Element ID - ${elementId}`);
        }

        /// Drop function
        function drop(ev) {
            ev.preventDefault();
            
            // Get the target field from the dragged data
            const fieldTarget = ev.dataTransfer.getData("text");

            // Get the elementId from the drag event if it exists
            let elementId = ev.dataTransfer.getData("element-id");

            // If no existing ID, generate a new one
            if (!elementId) {
                elementId = globalCounter++;
                console.log(`Generated new elementId: ${elementId}`);
            } else {
                console.log(`Reusing existing elementId: ${elementId}`);
            }

            // Get the position of the drop relative to the fileDisplay area
            const rect = fileDisplay.getBoundingClientRect(); // Get the bounds of the file display area
            const left = ev.clientX - rect.left; // Adjust based on the bounding rect
            const top = ev.clientY - rect.top;

            // Check if an element with this ID already exists
            const existingElement = document.querySelector(`[data-id="${elementId}"]`);
            
            if (existingElement) {
                // Remove the existing element before creating a new one
                removeField(existingElement);
                console.log(`Removed Existing Element: ID - ${elementId}`);
            }

            // Create the dropped element
            const droppedElement = createDroppedElement(fieldTarget, left, top);
            droppedElement.setAttribute('data-id', elementId);
            
            // Append the dropped element to the file display
            fileDisplay.appendChild(droppedElement);

            // Debugging: Log the dropped element information
            console.log(`Dropped New Element: ID - ${elementId}, Target - ${fieldTarget}, Position - (${left}, ${top})`);

            // Save to localStorage
            saveToStorage(elementId, fieldTarget, left, top, currentPage);
        }

        // Save to localStorage
        function saveToStorage(id, target, x, y, page) {
            // Ensure the id is always stored as a number
            const numericId = parseInt(id, 10);
            
            const elementData = { id: numericId, target, x, y, page };
            let storedElements = JSON.parse(localStorage.getItem('droppedElements')) || [];

            // Check if the element with the same numeric ID already exists in the storage
            const existingElementIndex = storedElements.findIndex(element => element.id === numericId);

            if (existingElementIndex !== -1) {
                // If the element exists, update its x and y values
                storedElements[existingElementIndex].x = x;
                storedElements[existingElementIndex].y = y;

                // Debugging: Log the updated element data
                console.log(`Updated Element Data (ID: ${numericId}):`, storedElements[existingElementIndex]);
            } else {
                // If the element doesn't exist, add it to the stored elements
                storedElements.push(elementData);

                // Debugging: Log the new element data being saved
                console.log(`New Element Data:`, elementData);
            }

            // Save the updated array back to localStorage
            localStorage.setItem('droppedElements', JSON.stringify(storedElements));

            // Debugging: Log all elements currently in localStorage
            console.log(`All Stored Elements:`, storedElements);
        }
        
        // Create dropped element
        function createDroppedElement(fieldTarget, left, top) {
            const droppedElement = document.createElement('div');
            droppedElement.classList.add("dropped");
            droppedElement.style.position = 'absolute'; // Ensure it's positioned absolutely
            droppedElement.style.left = `${left}px`;
            droppedElement.style.top = `${top}px`;
            droppedElement.setAttribute('draggable', 'true');

            // Attach the dragNew function to ondragstart
            droppedElement.ondragstart = function(ev) {
                dragNew(ev); // Call the dragNew function when dragging starts
            };

            // Set data attributes
            droppedElement.setAttribute('data-target', fieldTarget);

            // Check what type of field is being dropped and handle accordingly
            if (fieldTarget.startsWith("image")) {
                // Handle image element
                const imgElement = document.getElementById(fieldTarget).querySelector('img').cloneNode(true);
                imgElement.src = document.getElementById(fieldTarget + 'Preview').src; // Ensure the src is set
                droppedElement.appendChild(imgElement);
            } else if (fieldTarget.startsWith("signature")) {
                // Handle signature element
                const signaturePreviewId = `${fieldTarget}Preview`; // Construct the correct preview ID
                const signaturePreview = document.getElementById(signaturePreviewId); // Signature preview image
                
                if (signaturePreview && signaturePreview.src) {  // Check if element and src exist
                    const imgElement = new Image(); // Create a new Image element
                    imgElement.src = signaturePreview.src; // Set the image src to the signature data URL
                    imgElement.style.maxWidth = '100px'; // Example size for the signature image
                    imgElement.style.maxHeight = '50px';
                    droppedElement.appendChild(imgElement); // Append the image to the dropped element
                } else {
                    console.error(`Signature preview element not found for target: ${fieldTarget}`);
                }
            } else {
                // Handle other text-based elements
                const inputValue = document.getElementById(fieldTarget)?.value || '';
                droppedElement.innerHTML = `<span>${inputValue}</span>`;
            }

            // Add a remove button
            droppedElement.innerHTML += `<span class="remove-btn" onclick="removeField2(this)">X</span>`;

            // Add resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            droppedElement.appendChild(resizeHandle);

            // Enable resizing
            makeResizable(droppedElement);

            // Debugging: Log the created dropped element HTML
            console.log(`Dropped Element Created with Target: ${fieldTarget}`);
            console.log('Dropped Element HTML:', droppedElement.outerHTML); // Log the complete HTML structure

            return droppedElement;
        }

        // Remove field function
        function removeField(element) {
            // Remove the element from the DOM
            if (element) {
                element.parentNode.removeChild(element);
                console.log(`Removed Element from DOM:`, element);
            }
        }

        // Remove field
        function removeField2(element) {
            const parent = element.parentElement;
            const elementId = parent.getAttribute('data-id'); // Ensure this is a string

            // Retrieve stored elements from localStorage
            let storedElements = JSON.parse(localStorage.getItem('droppedElements')) || [];
            
            console.log('Stored Elements Before Filtering:', JSON.stringify(storedElements, null, 2));
            console.log('Element ID to Remove:', elementId);

            // Remove the element from localStorage, ensuring type consistency
            storedElements = storedElements.filter(el => String(el.id) !== String(elementId));

            console.log('Stored Elements After Filtering:', JSON.stringify(storedElements, null, 2));

            // Update localStorage
            localStorage.setItem('droppedElements', JSON.stringify(storedElements));
            
            // Log to confirm the change in localStorage
            console.log('localStorage After Update:', JSON.parse(localStorage.getItem('droppedElements')));

            // Remove the element from the DOM
            removeField(element);
            parent.remove();
            console.log(`Removed Element from DOM:`, element);
        }



        function dragNew(ev) {
            const targetData = ev.target.getAttribute('data-target') || ev.target.parentElement.getAttribute('data-target');
            ev.dataTransfer.setData("text", targetData);

            const elementId = ev.target.getAttribute('data-id') || ev.target.parentElement.getAttribute('data-id') || '';
            ev.dataTransfer.setData("element-id", elementId);

            // Debugging: Log the dragged element and its attributes
            console.log(`Dragged Element:`, ev.target);
            console.log(`Data ID from Target: ${ev.target.getAttribute('data-id')}`);
            console.log(`Data ID from Parent: ${ev.target.parentElement.getAttribute('data-id')}`);
            console.log(`New Drag Function: Target Data - ${targetData}, Element ID - ${elementId}`);
        }


        // Existing drag function for checking during drag
        function dragexesit(ev) {
            const targetData = ev.target.getAttribute('data-target');
            const elementId = ev.target.getAttribute('data-id');
            
            // Set the data for the drag operation
            ev.dataTransfer.setData("text", targetData);
            ev.dataTransfer.setData("element-id", elementId);

            // Debugging: Log the data being transferred
            console.log(`Dragging Exisit: Target Data - ${targetData}, Element ID - ${elementId}`);
        }


        // Make element resizable
        function makeResizable(element) {
            let startX, startY, startWidth, startHeight;

            function initResize(e) {
                startX = e.clientX;
                startY = e.clientY;
                startWidth = parseInt(document.defaultView.getComputedStyle(element).width, 10);
                startHeight = parseInt(document.defaultView.getComputedStyle(element).height, 10);
                document.documentElement.addEventListener('mousemove', resize);
                document.documentElement.addEventListener('mouseup', stopResize);
            }

            function resize(e) {
                element.style.width = (startWidth + e.clientX - startX) + 'px';
                element.style.height = (startHeight + e.clientY - startY) + 'px';
            }

            function stopResize() {
                document.documentElement.removeEventListener('mousemove', resize);
                document.documentElement.removeEventListener('mouseup', stopResize);
            }

            element.querySelector('.resize-handle').addEventListener('mousedown', initResize);
        }

        // Load PDF and render
        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                // Clear previous history
                clearHistory();
                const fileReader = new FileReader();
                fileReader.onload = function() {
                    const typedArray = new Uint8Array(this.result);
                    pdfjsLib.getDocument(typedArray).promise.then(function(pdf) {
                        pdfDoc = pdf;
                        totalPages = pdf.numPages;
                        renderPage(currentPage);
                    });
                };
                fileReader.readAsArrayBuffer(file);
            }
        });

        function restoreElements(pageNum) {
            const storedElements = JSON.parse(localStorage.getItem('droppedElements')) || [];
            // Clear existing elements before restoring
            const existingElements = document.querySelectorAll('.dropped');
            existingElements.forEach(el => el.remove());

            // Restore elements for the current page
            storedElements.forEach(element => {
                if (element.page === pageNum) {
                    const droppedElement = createDroppedElement(element.target, element.x, element.y);
                    droppedElement.setAttribute('data-id', element.id);
                    fileDisplay.appendChild(droppedElement);
                }
            });
        }

        // Render PDF Page
        function renderPage(pageNum) {
            pdfDoc.getPage(pageNum).then(function(page) {
                const scale = 1.5;
                const viewport = page.getViewport({ scale: scale });
                const canvas = document.createElement('canvas');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const context = canvas.getContext('2d');
                const renderContext = { canvasContext: context, viewport: viewport };

                // Clear the file display area and render the correct page
                fileDisplay.innerHTML = '';
                page.render(renderContext).promise.then(function() {
                    fileDisplay.appendChild(canvas);
                    restoreElements(pageNum); // Restore elements for the current page after rendering the page
                });
            });
        }


        // Change page
        function changePage(direction) {
            if (pdfDoc) {
                currentPage += direction;
                if (currentPage < 1) currentPage = 1;
                if (currentPage > totalPages) currentPage = totalPages;
                renderPage(currentPage);
            }
        }

        document.querySelector('button[value="Fill and Download"]').addEventListener('click', function () {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF();
            let promises = [];

            // Capture each page in sequence with dropped labels
            let capturePage = (pageNum) => {
                return new Promise((resolve) => {
                    // Render each page one by one
                    pdfDoc.getPage(pageNum).then(function (page) {
                        const scale = 1.5;
                        const viewport = page.getViewport({ scale: scale });
                        const canvas = document.createElement('canvas');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const context = canvas.getContext('2d');
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };

                        // Clear the display area and render the correct page
                        fileDisplay.innerHTML = '';
                        page.render(renderContext).promise.then(function () {
                            // After rendering the page, restore the dropped elements
                            fileDisplay.appendChild(canvas);
                            restoreElements(pageNum); // Pass the pageNum to restore unique elements

                            // Wait for the dropped elements to be in place before capturing
                            setTimeout(() => {
                                // Capture the page content with the dropped elements using html2canvas
                                html2canvas(fileDisplay).then((canvas) => {
                                    const imgData = canvas.toDataURL('image/jpeg');
                                    if (pageNum === 1) {
                                        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297); // Add the first page
                                    } else {
                                        pdf.addPage();
                                        pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297); // Add subsequent pages
                                    }
                                    resolve(); // Resolve after adding the page to PDF
                                });
                            }, 500); // Ensure a slight delay for elements to render properly
                        });
                    });
                });
            };

            // Sequentially capture and download all pages
            let promiseChain = Promise.resolve();
            for (let page = 1; page <= totalPages; page++) {
                promiseChain = promiseChain.then(() => capturePage(page));
            }

            promiseChain.then(() => {
                pdf.save("filled_form.pdf");
            });

            promiseChain.then(() => {
                // Convert the generated PDF to Blob format for uploading
                const pdfBlob = pdf.output('blob');

                // Create FormData object to send the PDF to the server
                let formData = new FormData();
                formData.append('file', pdfBlob, 'filled_form.pdf');
                formData.append('name', 'Document Name'); // Add any additional data (like the document name)

                // Send the PDF to the server via AJAX
                fetch('upload_pdf.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('PDF successfully saved and link generated: ' + data.link);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error uploading PDF:', error));
            });
        });


        function capturePageAsImage(pageNum, pdf) {
            return new Promise((resolve) => {
                pdfDoc.getPage(pageNum).then(function(page) {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale: scale });
                    const canvas = document.createElement('canvas');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const context = canvas.getContext('2d');
                    const renderContext = { canvasContext: context, viewport: viewport };

                    page.render(renderContext).promise.then(function() {
                        html2canvas(fileDisplay).then(canvas => {
                            const imgData = canvas.toDataURL('image/jpeg');
                            if (pageNum === 1) {
                                pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
                            } else {
                                pdf.addPage();
                                pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297);
                            }
                            resolve();
                        });
                    });
                });
            });
        }

        // Handle image preview for uploaded images
        function previewImage(event, imgId) {
            const file = event.target.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(imgId + 'Preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Clear form fields
        function clearFormFields() {
            document.getElementById('dataForm').reset();
            document.getElementById('image1Preview').src = '';
            document.getElementById('image2Preview').src = '';
            document.getElementById('image3Preview').src = '';
            document.getElementById('image4Preview').src = '';
            document.getElementById('image5Preview').src = '';
            document.getElementById('signaturePreview').src = '';
        }

        // Clear history and reset UI
        document.getElementById('clear-btn').addEventListener('click', function() {
            clearHistory();
            clearFormFields();
        });

        // On window load, restore elements from local storage if needed
        window.onload = function() {
            // Optionally restore elements if a previous session exists
            const storedElements = JSON.parse(localStorage.getItem('droppedElements'));
            if (storedElements) {
                restoreElements();
            }
        };

        function clearHistory() {
            localStorage.removeItem('droppedElements');
            fileDisplay.innerHTML = '';
            console.log('History cleared.');
        }




        let signaturePadActive = false;

        // Open the signature pad when 'Add Signature' button is clicked
        function openSignaturePad() {
            document.getElementById('signaturePadContainer').style.display = 'block';
            document.getElementById('clearSignatureBtn').style.display = 'inline-block';
        }

        // Initialize the signature canvas
        const signatureCanvas = document.getElementById('signatureCanvas');
        const context = signatureCanvas.getContext('2d');  // Correct variable name
        let drawing = false;

        signatureCanvas.addEventListener('mousedown', startDrawing);
        signatureCanvas.addEventListener('mousemove', drawSignature);
        signatureCanvas.addEventListener('mouseup', stopDrawing);
        signatureCanvas.addEventListener('mouseout', stopDrawing);

        function startDrawing(event) {
            drawing = true;
            context.beginPath();
            context.moveTo(event.offsetX, event.offsetY);
        }

        function drawSignature(event) {
            if (!drawing) return;
            context.lineTo(event.offsetX, event.offsetY);
            context.stroke();
        }

        function stopDrawing() {
            drawing = false;
            context.closePath();
        }

        let signatureDiv; // Declare signatureDiv globally to access it later

        function saveSignature() {
            const signatureImage = signatureCanvas.toDataURL('image/png');

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
            
            // Remove the alt attribute if it exists
            signaturePreview.removeAttribute('alt');

            // Assign draggable properties to the signatureDiv
            signatureDiv.setAttribute('data-target', `signature-${globalCounter}`);
            signatureDiv.setAttribute('draggable', 'true');

            // Attach event listeners for dragging the signatureDiv
            signatureDiv.ondragstart = function(ev) {
                dragNew(ev); // Use your drag function
            };

            // Increment the global counter
            globalCounter++;

            // Hide the signature pad after saving
            document.getElementById('signaturePadContainer').style.display = 'none';

            console.log(`Signature saved and attached to div with ID: ${signatureDiv.id}`);
        }



        // Clear the signature canvas
        function clearSignature() {
            context.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
        }

    </script>

</body>
</html>

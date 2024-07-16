<?php
$servername = "localhost";
$username = "theglobalfx";
$password = "Forex@123";
$dbname = "TGF";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestData = json_decode(file_get_contents("php://input"), true);

    if (!$requestData) {
        echo "Error decoding JSON data";
        exit;
    }

    $strategyAC = $conn->real_escape_string($requestData['strategyName']);

    // Fetch unique combinations of AC_Number and WalletID from the client table
    $clientQuery = "SELECT DISTINCT AC_Number, WID FROM client WHERE TRIM(StrategyName) = '$strategyAC'";
    $clientResult = $conn->query($clientQuery);

    if ($clientResult && $clientResult->num_rows > 0) {
        while ($clientRow = $clientResult->fetch_assoc()) {
            $acNumber = $clientRow['AC_Number'];
            $walletId = $clientRow['WID'];

            foreach ($requestData['selectedData'] as $data) {
                $ticketNo = $conn->real_escape_string($data['ticketNo']);
                $symbol = $conn->real_escape_string($data['symbol']);
                $type = $conn->real_escape_string($data['type']);
                $volume = $conn->real_escape_string($data['volume']);
                $openPrice = $conn->real_escape_string($data['openPrice']);
                $openTime = $conn->real_escape_string($data['openTime']);
                $closePrice = $conn->real_escape_string($data['closePrice']);
                $closeTime = $conn->real_escape_string($data['closeTime']);
                $profit = $conn->real_escape_string($data['profit']);

                // Check if the record already exists
                $checkQuery = "SELECT COUNT(*) as count FROM statements WHERE PositionID = '$ticketNo' AND AC_Number = '$acNumber' AND WalletID = '$walletId'";
                $checkResult = $conn->query($checkQuery);

                if ($checkResult) {
                    $row = $checkResult->fetch_assoc();
                    $recordCount = $row['count'];

                    if ($recordCount > 0) {
                        echo "Record already exists for AC_Number = $acNumber and WalletID = $walletId, TicketID = $ticketNo<br>";
                    } else {
                        // Fetch initial balance
                        $sqlInitial = "SELECT InitialBalance FROM client WHERE AC_Number = '$acNumber'";
                        $resultInitial = $conn->query($sqlInitial);

                        if ($resultInitial) {
                            $rowInitial = $resultInitial->fetch_assoc();
                            $initialBalance = $rowInitial["InitialBalance"];
                            $noMulti = ($initialBalance / 500);

                            $lot = $volume * $noMulti;
                            $profitMulti = $profit * $noMulti;

                            // Insert the new record
                            $stmt = $conn->prepare("INSERT INTO statements (PositionID, Symbol, Type1, Volume, OpenPrice, OpenTime, ClosePrice, CloseTime, Profit, AC_Number, WalletID) 
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                            $stmt->bind_param("sssssssssss", $ticketNo, $symbol, $type, $lot, $openPrice, $openTime, $closePrice, $closeTime, $profitMulti, $acNumber, $walletId);

                            if ($stmt->execute()) {
                                echo "Insert successfully for AC_Number = $acNumber, WalletID = $walletId, TicketID = $ticketNo<br>";
                            } else {
                                echo "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            echo "Error fetching initial balance: " . $conn->error;
                        }
                    }
                } else {
                    echo "Error checking existing records: " . $conn->error;
                }
            }
        }
    } else {
        echo "No accounts found in the client table.";
    }

    echo "Data insertion process completed!";
}

$conn->close();
?>

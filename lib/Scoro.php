<?php

namespace Cli;

use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;

class Scoro
{
    protected string $apiKey;
    protected string $endpoint;
    protected string $companyAccountId;

    public function __construct()
    {
        $this->apiKey = "ScoroAPI_dedbad94bc41bd1";
        $this->endpoint = "https://homeassignment.scoro.com";
        $this->companyAccountId = "apiplayground";
    }

    /**
     * import the legacy xml file
     * @param $xml_file_input
     */
    public function import($xml_file_input)
    {
        $csv_file_output = "/application/csv_output.csv";
        $xml = simplexml_load_file($xml_file_input);
        if (file_exists($csv_file_output)) {
            if (!unlink($csv_file_output)) {
                echo("$csv_file_output cannot be deleted due to an error");
            } else {
                echo("$csv_file_output has been deleted");
            }
        }
        $output_file = fopen($csv_file_output, 'w');

        $header = false;

        foreach ($xml as $key => $value) {
            if (!$header) {
                fputcsv($output_file, array_keys(get_object_vars($value)));
                $header = true;
            }
            fputcsv($output_file, get_object_vars($value));
        }

        fclose($output_file);
    }

    private function callAPI($method, $endpoint, $data)
    {
        $url = $this->endpoint . $endpoint;
        $data = [
            "lang" => "eng",
            "company_account_id" => $this->companyAccountId,
            "apiKey" => $this->apiKey,
            "detailed_response" => 1,
            "request" => $data
        ];
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case "GET":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // EXECUTE:
        $result = curl_exec($curl);
        if (!$result) {
            die("Connection Failure");
        }
        curl_close($curl);
        return $result;
    }

    private function getContactList(): array
    {
        $result = $this->callAPI(
            "POST",
            '/api/v2/contacts/list/',
            []
        );
        $result = json_decode($result);
        if ($result->status === 'OK') {
            return $result->data;
        }
        return [];
    }

    private function compareCategory($recordCategory, $contactCategory): bool
    {
        if (str_contains($contactCategory, $recordCategory)) {
            return true;
        }
        return false;
    }

    private function addComment($comment, $objectId)
    {
        $result = $this->callAPI("POST",
            "/api/v2/comments/modify/",
            [
                "module" => "contacts",
                "object_id" => $objectId,
                "comment" => $comment
            ]
        );
    }

    /**
     * @throws CannotInsertRecord
     */
    private function compareContactList(Reader $csv, $contactList)
    {
        $wrongCategoryContacts = [];
        foreach ($contactList as $contact) {
            foreach ($csv as $record) {
                if ($contact->search_name === $record['name']) {
                    if (!$this->compareCategory($record['clientCategory'], $contact->cat_name)) {
                        $wrongCategoryContacts[] = $contact;
                        $this->addComment("Wrong Category Details (OU)", $contact->id_code);
                    }
                }
            }
        }
        $this->putToCsv($wrongCategoryContacts);
    }

    /**
     * @param string $csv_file
     * @return Reader
     * @throws Exception
     */
    public function readCsv(string $csv_file = '/application/csv_output.csv'): Reader
    {
        //load the CSV document from a file path
        $csv = Reader::createFromPath($csv_file, 'r');
        $csv->setHeaderOffset(0);
        return $csv;
    }

    /**
     * @throws Exception
     */
    public function compareList()
    {
        $csv = $this->readCsv();
        $contactList = $this->getContactList();
        $this->compareContactList($csv, $contactList);
    }

    /**
     * @throws CannotInsertRecord
     */
    private function putToCsv(array $wrongCategoryContacts)
    {
        $writer = Writer::createFromPath('/application/missed_categories.csv', 'w+');
        $header = ["contact_id", "name", "lastname", "search_name", "contact_type", "id_code"];
        $writer->insertOne($header);
        foreach ($wrongCategoryContacts as $contact) {
            $writer->insertOne([$contact->contact_id, $contact->name, $contact->lastname, $contact->search_name, $contact->contact_type, $contact->id_code]);
        }
    }
}
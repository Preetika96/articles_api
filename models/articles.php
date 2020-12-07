<?php
class Articles
{
    // Articles Request Variables
    
    private $connection;
    private $requestMethod;
    private $input;
    private $parameters;
    private $uri;

    // Constructor
    
    public function __construct($db, $requestMethod, $data, $parameters, $uri)
    {
        $this->connection = $db;
        $this->requestMethod = $requestMethod;
        $this->input = $data;
        $this->uri = $uri;

        parse_str($parameters, $this->parameters);
    }

    // Process Request

    public function processRequest()
    {
        switch ($this->requestMethod) 
        {
            case 'GET':
                $response = $this->getArticles();
                break;
            case 'POST':
                $response = $this->addArticles();
                break;
            case 'PUT':
                $response = $this->updateArticle();
                break;
            case 'DELETE':
                $response = $this->deleteArticle();
                break;
            default:
                $response = $this->invalidRequest();
                break;
        }

        header($response['status_code_header']);

        if ($response['body']) 
        {
            echo $response['body'];
        }
    }

    // Invalid Request

    private function invalidRequest()
    {
        $response['status_code_header'] = 'HTTP/1.1 404 Not Found';
        $response['body'] = null;
        return $response;
    }

    // GET Request

    private function getArticles()
    { 
        $condition = [];
        $parameter_list = [];

        $getOne = 0;

        // Get One Element
        if(isset($this->uri[2]))
        {
            $condition[] = "a.id = :id";
            $parameter_list[":id"] = $this->uri[2];
            $getOne = 1;
        }

        // Get Conditions (Based on Parameters)
        else if(count($this->parameters) > 0)
        {
            if(isset($this->parameters['id']) && $this->parameters['id'] != '')
            {
                $condition[] = "a.id = :id";
                $parameter_list[":id"] = $this->parameters['id'];
            }

            if(isset($this->parameters['location']) && $this->parameters['location'] != '')
            {
                $condition[] = "l.location LIKE :location";
                $parameter_list[":location"] = "%".urldecode($this->parameters['location'])."%";
            }

            if(isset($this->parameters['author']) && $this->parameters['author'] != '')
            {
                $condition[] = "au.name LIKE :a_name";
                $parameter_list[":a_name"] = "%".urldecode($this->parameters['author'])."%";
            }

            if(isset($this->parameters['publisher']) && $this->parameters['publisher'] != '')
            {
                $condition[] = "p.name LIKE :p_name";
                $parameter_list[":p_name"] = "%".urldecode($this->parameters['publisher'])."%";
            }

            if(isset($this->parameters['keyword']) && $this->parameters['keyword'] != '')
            {
                $condition[] = "k.keyword LIKE :keyword";
                $parameter_list[":keyword"] = "%".urldecode($this->parameters['keyword'])."%";
            }

            if(isset($this->parameters['section']) && $this->parameters['section'] != '')
            {
                $condition[] = "a.section LIKE :section";
                $parameter_list[":section"] = "%".urldecode($this->parameters['section'])."%";
            }            
        }

        $condition = count($condition) > 0 ? implode(" AND ", $condition) : "";
        $condition = $condition != "" ? " AND (".$condition.")" : ""; 

        try
        {
            $statement = "SELECT a.*, au.name as author_name, au.url as author_url, l.location, p.name as publisher_name, p.url as publisher_url, lg.url as logo, lg.width, lg.height, GROUP_CONCAT(k.keyword) as keywords FROM articles a LEFT JOIN authors au ON a.id = au.article_id LEFT JOIN locations l ON a.id = l.article_id LEFT JOIN publishers p ON a.id = p.article_id LEFT JOIN logos lg ON p.id = lg.publisher_id LEFT JOIN keywords k ON a.id = k.article_id WHERE a.valid = '1' ".$condition." GROUP BY a.id";
           
            $statement = $this->connection->prepare($statement);

            $statement->execute($parameter_list);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            
            if($getOne == 0 || $statement->rowCount() > 0)
            {
                $final_output = [];

                foreach($result as $record)
                {
                    $output = $this->formatArticleOutput($record);

                    $final_output[] = $output;
                    $output = [];
                }

                $code = 'HTTP/1.1 200 OK';
                $body = json_encode($final_output);
            }
            else
            {
                $code = 'HTTP/1.1 404 Not Found';
                $body = null;
            }
        }
        catch (\PDOException $e) 
        {
            $error = $e->getMessage(); // Can Store it in DB or Write in File as Log
            $code = 'HTTP/1.1 400 Bad Request';
            $body = "Bad GET Request.";
        }

        return array("status_code_header" => $code, "body" => $body);
    }

    // DELETE Request

    private function deleteArticle()
    {
        if(isset($this->uri[2]) && $this->uri[2] != "" && $this->uri[2] != null)
        {
            $article_id = $this->uri[2];

            try
            {
                $statement = "SELECT * FROM articles WHERE id = :id";
                $statement = $this->connection->prepare($statement);

                $parameter_list = array(
                    ":id" => $article_id
                );

                $statement->execute($parameter_list);

                if($statement->rowCount() > 0)
                {                
                    $statement = "UPDATE articles SET valid = :status WHERE id = :id";
                    $statement = $this->connection->prepare($statement);

                    $parameter_list = array(
                        ":id" => $article_id,
                        ":status" => 0
                    );

                    $statement->execute($parameter_list);

                    $code = 'HTTP/1.1 200 OK';
                    $body = "Article has been soft-deleted successfully.";
                }
                else
                {
                    $code = 'HTTP/1.1 404 Not Found';
                    $body = null;
                }
            }
            catch (\PDOException $e) 
            {
                $error = $e->getMessage(); // Can Store it in DB or Write in File as Log
                $code = 'HTTP/1.1 400 Bad Request';
                $body = "Bad DELETE Request.";
            }
        }
        else
        {
            $code = 'HTTP/1.1 400 Bad Request';
            $body = "Bad DELETE Request.";
        }

        return array("status_code_header" => $code, "body" => $body);
    }

    // POST Request

    private function addArticles()
    {
        $data = $this->input;

        if(isset($data->input) && gettype($data->input) === 'array')
        {
            foreach($data->input as $record)
            {
                $record = (array) $record;

                if($this->validateArticle(1, $record) === 1)
                {
                    $formatted_input = $this->formatArticle(1, $record);

                    try
                    {    
                        // Articles

                        $statement = "INSERT INTO articles(image, url, headline, created_on, modified_on, published_on, lang, section, body, valid) VALUES (:image, :url, :headline, :created_on, :modified_on, :published_on, :lang, :section, :body, :valid)";
                        $statement = $this->connection->prepare($statement);
                        $statement->execute($formatted_input['articles']);

                        $insert_id = $this->connection->lastInsertId();

                        // Authors

                        $insert_input = $formatted_input['authors'];
                        $insert_input[":article_id"] = $insert_id;
                        
                        $statement = "INSERT INTO authors(article_id, name, url) VALUES (:article_id, :name, :url)";
                        $statement = $this->connection->prepare($statement);
                        $statement->execute($insert_input);

                        // Keywords

                        $statement = "INSERT INTO keywords(article_id, keyword) VALUES (:article_id, :keyword)";
                        $statement = $this->connection->prepare($statement);

                        $insert_input = $formatted_input['keywords'];
                        
                        foreach($insert_input as &$rec)
                        {
                            $rec[":article_id"] = $insert_id;
                            $statement->execute($rec);
                        }

                        // Publishers

                        $insert_input = $formatted_input['publishers'];
                        $insert_input[":article_id"] = $insert_id;

                        $statement = "INSERT INTO publishers(article_id, name, url) VALUES (:article_id, :name, :url)";
                        $statement = $this->connection->prepare($statement);
                        $statement->execute($insert_input);

                        $secondary_insert_id = $this->connection->lastInsertId();

                        // Logos
                        
                        $insert_input = $formatted_input['logos'];
                        $insert_input[":publisher_id"] = $secondary_insert_id;

                        $statement = "INSERT INTO logos(publisher_id, url, width, height) VALUES (:publisher_id, :url, :width, :height)";
                        $statement = $this->connection->prepare($statement);
                        $statement->execute($insert_input);
                        

                        //  Locations

                        $insert_input = $formatted_input['locations'];
                        $insert_input[":article_id"] = $insert_id;
                    
                        $statement = "INSERT INTO locations(article_id, location) VALUES (:article_id, :location)";
                        $statement = $this->connection->prepare($statement);
                        $statement->execute($insert_input);

                        // Get Added Output

                        $statement = "SELECT a.*, au.name as author_name, au.url as author_url, l.location, p.name as publisher_name, p.url as publisher_url, lg.url as logo, lg.width, lg.height, GROUP_CONCAT(k.keyword) as keywords FROM articles a LEFT JOIN authors au ON a.id = au.article_id LEFT JOIN locations l ON a.id = l.article_id LEFT JOIN publishers p ON a.id = p.article_id LEFT JOIN logos lg ON p.id = lg.publisher_id LEFT JOIN keywords k ON a.id = k.article_id WHERE a.valid = '1' AND a.id = :id GROUP BY a.id";
           
                        $statement = $this->connection->prepare($statement);

                        $statement->execute(array(":id" => $insert_id));
                        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

                        $result = $this->formatArticleOutput($result[0]);

                        $code = 'HTTP/1.1 200 OK';
                        $body = array("message" => "Article has been added successfully.", "data" => $result);
                        $body = json_encode($body);
                    }
                    catch (\PDOException $e) 
                    {
                        $error = $e->getMessage(); // Can Store it in DB or Write in File as Log
                        $code = 'HTTP/1.1 400 Bad Request';
                        $body = "Bad POST Request.";
                    }
                }
                else
                {
                    $code = 'HTTP/1.1 400 Bad Request';
                    $body = "Bad POST Request.";
                    break;
                }
            }
        }
        else
        {
            $code = 'HTTP/1.1 400 Bad Request';
            $body = "Bad POST Request.";
        }

        return array("status_code_header" => $code, "body" => $body);
        
    }

    // PUT Request

    private function updateArticle()
    {
        $data = $this->input;

        if((isset($this->uri[2]) && $this->uri[2] != "" && $this->uri[2] != null) && (isset($data->input) && gettype($data->input) === 'object'))
        {
            $article_id = $this->uri[2];

            $statement = "SELECT * FROM articles WHERE id = :id";
            $statement = $this->connection->prepare($statement);

            $parameter_list = array(
                ":id" => $article_id
            );

            $statement->execute($parameter_list);

            if($statement->rowCount() > 0)
            {    
                $record = (array) $data->input;

                if($this->validateArticle(2, $record) === 1)
                {
                    $formatted_input = $this->formatArticle(2, $record);

                    try
                    {    
                        // Articles

                        if(count($formatted_input['update_articles']) > 0)
                        {
                            $update_input = $formatted_input['articles'];
                            $update_input[":id"] = $article_id;

                            $statement = "UPDATE articles SET ".implode(", ", $formatted_input['update_articles'])." WHERE id = :id";

                            $statement = $this->connection->prepare($statement);
                            $statement->execute($update_input);
                        }

                        // Authors

                        if(count($formatted_input['update_authors']) > 0)
                        {
                            $update_input = $formatted_input['authors'];
                            $update_input[":article_id"] = $article_id;
                        
                            $statement = "UPDATE authors SET ".implode(", ", $formatted_input['update_authors'])." WHERE article_id = :article_id";

                            $statement = $this->connection->prepare($statement);
                            $statement->execute($update_input);
                        }

                        // Keywords

                        $update_input = $formatted_input['keywords'];

                        if(count($update_input) > 0)
                        {
                            $statement = "DELETE FROM keywords WHERE article_id = :article_id";
                            $statement = $this->connection->prepare($statement);
                            $statement->execute(array(":article_id" => $article_id));

                            $statement = "INSERT INTO keywords(article_id, keyword) VALUES (:article_id, :keyword)";
                            $statement = $this->connection->prepare($statement);

                            foreach($update_input as &$rec)
                            {
                                $rec[":article_id"] = $article_id;
                                $statement->execute($rec);
                            }
                        }

                        // Publishers

                        if(count($formatted_input['update_publishers']) > 0)
                        {
                            $update_input = $formatted_input['publishers'];
                            $update_input[":article_id"] = $article_id;
                        
                            $statement = "UPDATE publishers SET ".implode(", ", $formatted_input['update_publishers'])." WHERE article_id = :article_id";

                            $statement = $this->connection->prepare($statement);
                            $statement->execute($update_input);
                        }

                        // Logos
                        
                        if(count($formatted_input['update_logos']) > 0)
                        {
                            $statement = "SELECT id FROM publishers WHERE article_id = :article_id";
                            $statement = $this->connection->prepare($statement);
                            $statement->execute(array(":article_id" => $article_id));
                            $publisher_id = $statement->fetchColumn();

                            $update_input = $formatted_input['logos'];
                            $update_input[":publisher_id"] = $publisher_id;

                            $statement = "UPDATE logos SET ".implode(", ", $formatted_input['update_logos'])." WHERE publisher_id = :publisher_id";

                            $statement = $this->connection->prepare($statement);
                            $statement->execute($update_input);
                        }

                        //  Locations

                        if(count($formatted_input['update_locations']) > 0)
                        {
                            $update_input = $formatted_input['locations'];
                            $update_input[":article_id"] = $article_id;
                        
                            $statement = "UPDATE locations SET ".implode(", ", $formatted_input['update_locations'])." WHERE article_id = :article_id";

                            $statement = $this->connection->prepare($statement);
                            $statement->execute($update_input);
                        }

                        // Get Updated Output

                        $statement = "SELECT a.*, au.name as author_name, au.url as author_url, l.location, p.name as publisher_name, p.url as publisher_url, lg.url as logo, lg.width, lg.height, GROUP_CONCAT(k.keyword) as keywords FROM articles a LEFT JOIN authors au ON a.id = au.article_id LEFT JOIN locations l ON a.id = l.article_id LEFT JOIN publishers p ON a.id = p.article_id LEFT JOIN logos lg ON p.id = lg.publisher_id LEFT JOIN keywords k ON a.id = k.article_id WHERE a.valid = '1' AND a.id = :id GROUP BY a.id";
           
                        $statement = $this->connection->prepare($statement);

                        $statement->execute(array(":id" => $article_id));
                        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

                        $result = $this->formatArticleOutput($result[0]);

                        $code = 'HTTP/1.1 200 OK';
                        $body = array("message" => "Article has been updated successfully.", "data" => $result);
                        $body = json_encode($body);
                    }
                    catch (\PDOException $e) 
                    {
                        $error = $e->getMessage(); // Can Store it in DB or Write in File as Log
                        $code = 'HTTP/1.1 400 Bad Request';
                        $body = "Bad PUT Request.";
                    }
                }
                else
                {
                    $code = 'HTTP/1.1 400 Bad Request';
                    $body = "Bad PUT Request.";
                }
            }
            else
            {
                $code = 'HTTP/1.1 404 Not Found';
                $body = null;
            }
        }
        else
        {
            $code = 'HTTP/1.1 400 Bad Request';
            $body = "Bad PUT Request.";
        }

        return array("status_code_header" => $code, "body" => $body);
        
    }

    // Validate Input

    private function validateArticle($type, $record)
    {
        $valid = 1;

        if($type === 1)
        {
            $conditions = (!isset($record['url']) || (isset($record['url']) && $record['url'] == '')) || (!isset($record['headline']) || (isset($record['headline']) && $record['headline'] == '')) || (!isset($record['inLanguage']) || (isset($record['inLanguage']) && $record['inLanguage'] == '')) || (!isset($record['authorName']) || (isset($record['authorName']) && $record['authorName'] == '')) || (!isset($record['publisherName']) || (isset($record['publisherName']) && $record['publisherName'] == '')) || (!isset($record['keywords']) || (isset($record['keywords']) && count($record['keywords']) == 0)) || (!isset($record['articleSection']) || (isset($record['articleSection']) && $record['articleSection'] == '')) || (!isset($record['articleBody']) || (isset($record['articleBody']) && $record['articleBody'] == '')) || (!isset($record['logoUrl']) || (isset($record['logoUrl']) && $record['logoUrl'] == '')) || (!isset($record['contentLocation']) || (isset($record['contentLocation']) && $record['contentLocation'] == ''));

            $valid = $conditions ? 0 : 1;
        }
        else if($type === 2)
        {
            $conditions = (isset($record['url']) && $record['url'] == '') || (isset($record['headline']) && $record['headline'] == '') || (isset($record['inLanguage']) && $record['inLanguage'] == '') || (isset($record['authorName']) && $record['authorName'] == '') || (isset($record['publisherName']) && $record['publisherName'] == '') || (isset($record['keywords']) && count($record['keywords']) == 0) || (isset($record['articleSection']) && $record['articleSection'] == '') || (isset($record['articleBody']) && $record['articleBody'] == '') || (isset($record['logoUrl']) && $record['logoUrl'] == '') || (isset($record['contentLocation']) && $record['contentLocation'] == '');

            $valid = $conditions ? 0 : 1;
        }

        return $valid;
    }

    // Format Article (Input)

    private function formatArticle($type, $record)
    {
        $input = [];

        // Articles
        
        $input['articles'] = [];

        if(isset($record['image']))
        {
            $input['articles'][':image'] = $record['image'];

            if($type === 2)
            {
                $input['update_articles'][] = "image = :image";
            }
        }
        
        if(isset($record['url']))
        {
            $input['articles'][':url'] = $record['url'];

            if($type === 2)
            {
                $input['update_articles'][] = "url = :url";
            }
        }

        if(isset($record['headline']))
        {
            $input['articles'][':headline'] = $record['headline'];

            if($type === 2)
            {
                $input['update_articles'][] = "headline = :headline";
            }
        }

        if(isset($record['datePublished']))
        {
            $input['articles'][':published_on'] = $record['datePublished'];

            if($type === 2)
            {
                $input['update_articles'][] = "published_on = :published_on";
            }
        }

        if(isset($record['inLanguage']))
        {
            $input['articles'][':lang'] = $record['inLanguage'];

            if($type === 2)
            {
                $input['update_articles'][] = "lang = :lang";
            }
        }

        if(isset($record['articleSection']))
        {
            $input['articles'][':section'] = $record['articleSection'];

            if($type === 2)
            {
                $input['update_articles'][] = "section = :section";
            }
        }

        if(isset($record['articleBody']))
        {
            $input['articles'][':body'] = $record['articleBody'];

            if($type === 2)
            {
                $input['update_articles'][] = "body = :body";
            }
        }

        if($type === 1)
        {
            $input['articles'][':created_on'] = $input['articles'][':modified_on'] = date("Y-m-d H:i:s");
            $input['articles'][':valid'] = '1';
        }
        else if($type === 2)
        {
            $input['articles'][':modified_on'] = date("Y-m-d H:i:s");
            $input['update_articles'][] = "modified_on = :modified_on";
        }
        
        // Locations

        $input['locations'] = [];

        if(isset($record['contentLocation']))
        {
            $input['locations'][':location'] = $record['contentLocation'];

            if($type === 2)
            {
                $input['update_locations'][] = "location = :location";
            }
        }

        // Authors

        $input['authors'] = [];

        if(isset($record['authorName']))
        {
            $input['authors'][':name'] = $record['authorName'];

            if($type === 2)
            {
                $input['update_authors'][] = "name = :name";
            }
        }

        if(isset($record['authorUrl']))
        {
            $input['authors'][':url'] = $record['authorUrl'];

            if($type === 2)
            {
                $input['update_authors'][] = "url = :url";
            }
        }

        // Publishers

        $input['publishers'] = [];

        if(isset($record['publisherName']))
        {
            $input['publishers'][':name'] = $record['publisherName'];

            if($type === 2)
            {
                $input['update_publishers'][] = "name = :name";
            }
        }

        if(isset($record['publisherUrl']))
        {
            $input['publishers'][':url'] = $record['publisherUrl'];

            if($type === 2)
            {
                $input['update_publishers'][] = "url = :url";
            }
        }

        // Logo

        $input['logos'] = [];

        if(isset($record['logoUrl']))
        {
            $input['logos'][':url'] = $record['logoUrl'];

            if($type === 2)
            {
                $input['update_logos'][] = "url = :url";
            }
        }

        if(isset($record['logoWidth']))
        {
            $input['logos'][':width'] = $record['logoWidth'];

            if($type === 2)
            {
                $input['update_logos'][] = "width = :width";
            }
        }

        if(isset($record['logoHeight']))
        {
            $input['logos'][':height'] = $record['logoHeight'];

            if($type === 2)
            {
                $input['update_logos'][] = "height = :height";
            }
        }

        // Keywords

        $input['keywords'] = [];

        if(isset($record['keywords']))
        {
            foreach($record['keywords'] as $keyword)
            {
                $input['keywords'][] = array(":keyword" => $keyword);
            }
        }

        return $input;
    }

    // Format Output
    
    private function formatArticleOutput($record)
    {
        $output = [];

        $output['image'] = $record['image'];
        $output['url'] = $record['url'];
        $output['headline'] = $record['headline'];
        $output['dateCreated'] = date("Y-m-d\TH:i:s", strtotime($record['created_on']));
        $output['datePublished'] = date("Y-m-d\TH:i:s", strtotime($record['published_on']));
        $output['dateModified'] = date("Y-m-d\TH:i:s", strtotime($record['modified_on']));
        $output['inLanguage'] = $record['lang'];
        $output['contentLocation'] = array(
            "name" => $record['location']
        );
        $output['author'] = array(
            "name" => $record['author_name'],
            "url" => $record['author_url']
        );
        $output['publisher'] = array(
            "name" => $record['publisher_name'],
            "url" => $record['publisher_url'],
            "logo" => array(
                "url" => $record['logo'],
                "width" => $record['width'],
                "height" => $record['height']
            )
        );
        $output['keywords'] = explode(",", $record['keywords']);
        $output['articleSection'] = $record['section'];
        $output['articleBody'] = $record['body'];

        return $output;
    }

}
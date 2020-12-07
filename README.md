# articles_api
Covers GET, POST, PUT, DELETE APIs (Along with JWT Authentication)

1) Get a JWT using GET JWT API.
2) Generated JWT Token is to be attached to Authentication Header with type as
Bearer Token for using Articles API.
3) Note that, in current assignment – only basic security standards are handled.

GET JWT API

Description:
This API provides a JWT with configurations as follows
1) Valid Not Before: 60 Seconds (After Generation)
2) Expiry: 3600 Seconds (After Generation)

Static Key is a simple means to identify if the source from where request has been initiated
is a known source or not. Static Key will be present with both Client (Request Initiator) and
Server (Request Server).
If the Static Key sent in Payload does not match with the one stored in Server – the Source
will not be identified as a Known Source – and so JWT will not be generated.
Request Payload Encryption should be done to ensure Security. In this assignment,
encryption of Request Payload is not covered.
If token is used before it is activated or after it is expired, or if JWT is completely Invalid –
message will be sent accordingly in subsequent API Calls.

Articles API

Method: GET

Examples
1) https://preetikashetty.space/articles_api.php/8
2) https://preetikashetty.space/articles_api.php?id=6&section=sports

Descriptions
1) This API is used to return Articles.
2) You can either pass the article ID as URI or set parameters like id, location, section,
keyword, author and publisher.
3) If both are done, result will be shown only on basis of URI and parameters will not be
considered.
4) Multiple Parameters can be sent at a single time.
5) If Article with given id in URI is not present – then 404 will be returned. If id is passed
as id=11 in parameter – empty array will be returned.


Method: POST

Note: In you can send multiple articles in one go this way
{
 "input" : [{}, {},..]
}

Descriptions
1) This API is used to add articles
2) In every article – url, headline, inLanguage, authorName, publisherName, keywords,
articleSection, articleBody, logoUrl, contentLocation are mandatory parameters. If
these parameters are not set or their values are empty – 400 Bad Request will be
returned.

Method: PUT

Descriptions
1) This API is used to update articles
2) In any article – if url, headline, inLanguage, authorName, publisherName, keywords,
articleSection, articleBody, logoUrl, contentLocation parameters are set, then they
should have a proper value – they cannot be empty. Bad Request will be returned if
the restrictions are not followed.
3) If there is no article present with the ID given in URL, then 404 will be returned.

Method: DELETE

Descriptions
1) This API is used to delete articles. However, here we are performing Soft Delete.
2) If there is no valid article present with the ID given in URL, then 404 will be returned.

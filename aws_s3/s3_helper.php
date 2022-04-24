<?php
if (!function_exists('getS3Details')) {

    /**
     * Get all the necessary details to directly upload a private file to S3
     * asynchronously with JavaScript using the Signature V4.
     *
     * @param string $s3Bucket your bucket's name on s3.
     * @param string $region the bucket's location/region, see here for details: http://amzn.to/1FtPG6r
     * @param string $acl the visibility/permissions of your file, see details: http://amzn.to/18s9Gv7
     *
     * @return array ['url', 'inputs'] the forms url to s3 and any inputs the form will need.
     */
    function getS3Details($s3Bucket, $region, $acl = 'public-read')
    {

// Options and Settings
        $awsKey = AWS_ACCESS_KEY;
        $awsSecret = AWS_SECRET;

        $algorithm = "AWS4-HMAC-SHA256";
        $service = "s3";
        $date = gmdate("Ymd\THis\Z");
        $shortDate = gmdate("Ymd");
        $requestType = "aws4_request";
        $expires = "86400"; // 24 Hours
        $successStatus = "201";
        $url = "//{$s3Bucket}.{$service}-{$region}.amazonaws.com";

// Step 1: Generate the Scope
        $scope = [
            $awsKey,
            $shortDate,
            $region,
            $service,
            $requestType
        ];
        $credentials = implode('/', $scope);

// Step 2: Making a Base64 Policy
        $policy = [
            'expiration' => gmdate('Y-m-d\TG:i:s\Z', strtotime('+6 hours')),
            'conditions' => [
                ['bucket' => $s3Bucket],
                ['acl' => $acl],
                [
                    'starts-with',
                    '$key',
                    ''
                ],
                [
                    'starts-with',
                    '$Content-Type',
                    ''
                ],
                ['success_action_status' => $successStatus],
                ['x-amz-credential' => $credentials],
                ['x-amz-algorithm' => $algorithm],
                ['x-amz-date' => $date],
                ['x-amz-expires' => $expires],
            ]
        ];
        $base64Policy = base64_encode(json_encode($policy));

// Step 3: Signing your Request (Making a Signature)
        $dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . $awsSecret, true);
        $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

        $signature = hash_hmac('sha256', $base64Policy, $signingKey);

// Step 4: Build form inputs
// This is the data that will get sent with the form to S3
        $inputs = [
            'Content-Type' => '',
            'acl' => $acl,
            'success_action_status' => $successStatus,
            'policy' => $base64Policy,
            'X-amz-credential' => $credentials,
            'X-amz-algorithm' => $algorithm,
            'X-amz-date' => $date,
            'X-amz-expires' => $expires,
            'X-amz-signature' => $signature
        ];

        return compact('url', 'inputs');
    }
}
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2)
    {
        $base = log($bytes, 1000);
        $suffixes = array(
            '',
            'K',
            'M',
            'G',
            'T'
        );

        return round(pow(1000, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}

if (!function_exists('s3_file_upload')) {
    function s3_file_upload($file, $options = null)
    {
        $s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY,
                'secret' => AWS_SECRET,
            ],
        ]);
        try {
            $path_parts = pathinfo($file);
            $extension = $path_parts['extension'];
            $key = isset($options['key']) ? $options['key'] : time() . rand(100, 999) . '.' . $extension;
            $obj = $s3Client->putObject([
                'Bucket' => AWS_BUCKET,
                'Key' => 'product_file/'.$key,
                'Body' => fopen($file, 'r'),
                'ACL' => 'public-read',
                'ContentDisposition' => sprintf('attachment; filename="%s"', $extension['basename'])
            ]);
            return json_encode([
                'name' => $extension['basename'],
                'size' => filesize($file),
                'type' => '',
                'url' => $obj['ObjectURL'],
                'key' => 'product_file/'.$key,
                'note' => ''
            ]);
        } catch (Aws\S3\Exception\S3Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

if (!function_exists('s3_delete')) {
    function s3_delete($key)
    {
        $s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY,
                'secret' => AWS_SECRET,
            ],
        ]);
        $result = $s3Client->deleteObject(array(
            // Bucket is required
            'Bucket' => AWS_BUCKET,
            'Key' => $key,
        ));
        return $result;
    }
}

if (!function_exists('s3_multiple_upload')) {
    function s3_multiple_upload($field, $json = null)
    {
        $ci = &get_instance();
        $json = $json ? json_decode($json) : json_decode('[]');
        if (isset($_FILES[$field]['name'])) {
            foreach ($_FILES[$field]['name'] as $key => $value) {
                $tmp_name = $_FILES[$field]['tmp_name'][$key];
                $name = $_FILES[$field]['name'][$key];
                $type = $_FILES[$field]['type'][$key];
                $size = $_FILES[$field]['size'][$key];
                $error = $_FILES[$field]['error'][$key];

                if ($error === 0) {
                    $s3Client = new Aws\S3\S3Client([
                        'version' => 'latest',
                        'region' => AWS_REGION,
                        'credentials' => [
                            'key' => AWS_ACCESS_KEY,
                            'secret' => AWS_SECRET,
                        ],
                    ]);
                    try {
                        $path_parts = pathinfo($name);
                        $extension = $path_parts['extension'];

                        $key = time() . rand(100, 999) . '.' . $extension;
                        $obj = $s3Client->putObject([
                            'Bucket' => AWS_BUCKET,
                            'Key' => 'product_file/'.$key,
                            'Body' => fopen($tmp_name, 'r'),
                            'ACL' => 'public-read',
                            'ContentDisposition' => sprintf('attachment; filename="%s"', $name)
                        ]);
                        @$json[] = [
                            'name' => $name,
                            'size' => $size,
                            'type' => $type,
                            'url' => $obj['ObjectURL'],
                            'key' => 'product_file/'.$key,
                            'time' => date('Y-m-d H:i:s'),
                            'by' => $ci->auth->get_name(),
                            'note' => ''
                        ];
                    } catch (Aws\S3\Exception\S3Exception $e) {
                        continue;
                    }
                }
            }
        }
        return count($json) ? json_encode($json) : false;
    }
}

if (!function_exists('s3_upload')) {
    function s3_upload($field, $options = null)
    {
        $tmp_name = $_FILES[$field]['tmp_name'];
        $name = $_FILES[$field]['name'];
        $type = $_FILES[$field]['type'];
        $size = $_FILES[$field]['size'];
        $error = $_FILES[$field]['error'];

        if ($error === 0) {
            $s3Client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => AWS_REGION,
                'credentials' => [
                    'key' => AWS_ACCESS_KEY,
                    'secret' => AWS_SECRET,
                ],
            ]);

            try {
                $path_parts = pathinfo($name);
                $extension = $path_parts['extension'];

                $key = isset($options['key']) ? $options['key'] : time() . rand(100, 999) . '.' . $extension;
                $obj = $s3Client->putObject([
                    'Bucket' => AWS_BUCKET,
                    'Key' => 'product_file/'.$key,
                    'Body' => fopen($tmp_name, 'r'),
                    'ACL' => 'public-read',
                    'ContentDisposition' => sprintf('attachment; filename="%s"', $name)
                ]);
                return json_encode([
                    'name' => $name,
                    'size' => $size,
                    'type' => $type,
                    'url' => $obj['ObjectURL'],
                    'key' => 'product_file/'.$key,
                    'note' => ''
                ]);
            } catch (Aws\S3\Exception\S3Exception $e) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('s3_content_upload')) {
    function s3_content_upload($file, $content)
    {
        $s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY,
                'secret' => AWS_SECRET,
            ],
        ]);
        try {
            $obj = $s3Client->putObject([
                'Bucket' => AWS_BUCKET,
                'Key' => 'product_file/'.$file,
                'Body' => $content,
                'ACL' => 'public-read',
            ]);
            return $obj['ObjectURL'];
        } catch (Aws\S3\Exception\S3Exception $e) {
            return '';
        }
    }
}
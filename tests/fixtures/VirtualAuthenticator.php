<?php
/** Test-only WebAuthn transcript generator. Never used by the module. */
final class VirtualAuthenticator
{
    private $key;
    private string $id;
    private string $handle;
    public function __construct()
    {
        $this->key = openssl_pkey_new(['config' => __DIR__ . '/openssl.cnf', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->id = random_bytes(32);
    }
    private function encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private function client(array $options, string $origin, string $type): string
    {
        return json_encode(['type' => $type, 'challenge' => $options['challenge'], 'origin' => $origin, 'crossOrigin' => false]);
    }
    public function create(array $options, string $origin): array
    {
        $this->handle = $options['user']['id'];
        $ec = openssl_pkey_get_details($this->key)['ec'];
        $encoder = new CBOR\Encoder();
        $cose = $encoder->encode([1=>2,3=>-7,-1=>1,
            -2=>CBOR\ByteStringObject::create(str_pad($ec['x'],32,"\0",STR_PAD_LEFT)),
            -3=>CBOR\ByteStringObject::create(str_pad($ec['y'],32,"\0",STR_PAD_LEFT))]);
        $auth = hash('sha256', $options['rp']['id'], true) . chr(69) . pack('N',0) . str_repeat("\0",16) . pack('n',strlen($this->id)) . $this->id . $cose;
        $attestation = $encoder->encode(['fmt'=>'none','attStmt'=>new CBOR\MapObject(),'authData'=>CBOR\ByteStringObject::create($auth)]);
        return ['id'=>$this->encode($this->id),'rawId'=>$this->encode($this->id),'type'=>'public-key','response'=>[
            'clientDataJSON'=>$this->encode($this->client($options,$origin,'webauthn.create')),'attestationObject'=>$this->encode($attestation),'transports'=>['internal']]];
    }
    public function get(array $options, string $origin, int $counter = 1): array
    {
        $client = $this->client($options,$origin,'webauthn.get');
        $auth = hash('sha256',$options['rpId'],true) . chr(5) . pack('N',$counter);
        openssl_sign($auth . hash('sha256',$client,true),$signature,$this->key,OPENSSL_ALGO_SHA256);
        return ['id'=>$this->encode($this->id),'rawId'=>$this->encode($this->id),'type'=>'public-key','response'=>[
            'clientDataJSON'=>$this->encode($client),'authenticatorData'=>$this->encode($auth),'signature'=>$this->encode($signature),'userHandle'=>$this->handle]];
    }
}

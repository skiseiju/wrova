<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Wrova_AI_Provider_Service {

	private array $settings;

	public function __construct() {
		$this->settings = get_option( 'wrova_settings', [] );
	}

	public function generate( string $prompt, string $system_prompt = '', array $images = [] ): array|WP_Error {
		$provider = $this->settings['ai_provider'] ?? 'gemini';

		if ( 'gemini' === $provider ) {
			$result = $this->call_gemini( $prompt, $system_prompt, $images );
			if ( is_wp_error( $result ) ) {
				$result = $this->call_claude( $prompt, $system_prompt );
			}
		} else {
			$result = $this->call_claude( $prompt, $system_prompt );
			if ( is_wp_error( $result ) ) {
				$result = $this->call_gemini( $prompt, $system_prompt, $images );
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_json_response( $result['text'], $prompt, $system_prompt );
	}

	private function call_gemini( string $prompt, string $system_prompt, array $images ): array|WP_Error {
		$api_key = $this->settings['gemini_api_key'] ?? '';
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'Gemini API key 未設定' );
		}

		$model    = $this->settings['gemini_model'] ?? 'gemini-2.0-flash-exp';
		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$parts = [ [ 'text' => $prompt ] ];
		foreach ( $images as $image ) {
			if ( ! empty( $image['mime_type'] ) && ! empty( $image['data'] ) ) {
				$parts[] = [
					'inline_data' => [
						'mime_type' => $image['mime_type'],
						'data'      => $image['data'],
					],
				];
			}
		}

		$body = [
			'contents'        => [ [ 'parts' => $parts ] ],
			'generationConfig' => [
				'temperature'     => 0.7,
				'maxOutputTokens' => 4096,
			],
		];

		if ( ! empty( $system_prompt ) ) {
			$body['system_instruction'] = [ 'parts' => [ [ 'text' => $system_prompt ] ] ];
		}

		$response = wp_remote_post( $endpoint, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error( 'gemini_http_error', 'Gemini API 回傳 HTTP ' . $code, wp_remote_retrieve_body( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

		if ( null === $text ) {
			return new WP_Error( 'gemini_parse_error', 'Gemini 回傳格式無法解析' );
		}

		return [ 'text' => $text ];
	}

	private function call_claude( string $prompt, string $system_prompt ): array|WP_Error {
		$api_key = $this->settings['claude_api_key'] ?? '';
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'Claude API key 未設定' );
		}

		$model = $this->settings['claude_model'] ?? 'claude-sonnet-4-6';

		$body = [
			'model'      => $model,
			'max_tokens' => 4096,
			'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
		];

		if ( ! empty( $system_prompt ) ) {
			$body['system'] = $system_prompt;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error( 'claude_http_error', 'Claude API 回傳 HTTP ' . $code, wp_remote_retrieve_body( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $data['content'][0]['text'] ?? null;

		if ( null === $text ) {
			return new WP_Error( 'claude_parse_error', 'Claude 回傳格式無法解析' );
		}

		return [ 'text' => $text ];
	}

	private function parse_json_response( string $raw, string $original_prompt, string $system_prompt ): array|WP_Error {
		$decoded = json_decode( $raw, true );
		if ( null !== $decoded ) {
			return $decoded;
		}

		$trimmed = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
		$trimmed = preg_replace( '/\s*```$/', '', $trimmed );
		$decoded = json_decode( $trimmed, true );
		if ( null !== $decoded ) {
			return $decoded;
		}

		$retry_prompt  = $original_prompt . "\n\n請嚴格只回傳 JSON，不要加任何說明文字、markdown 格式或 code block。";
		$provider      = $this->settings['ai_provider'] ?? 'gemini';

		if ( 'gemini' === $provider ) {
			$result = $this->call_gemini( $retry_prompt, $system_prompt, [] );
		} else {
			$result = $this->call_claude( $retry_prompt, $system_prompt );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'json_parse_failed', 'AI 回傳內容無法解析為 JSON（retry 後仍失敗）' );
		}

		$retry_trimmed = preg_replace( '/^```(?:json)?\s*/i', '', trim( $result['text'] ) );
		$retry_trimmed = preg_replace( '/\s*```$/', '', $retry_trimmed );
		$decoded       = json_decode( $retry_trimmed, true );

		if ( null !== $decoded ) {
			return $decoded;
		}

		return new WP_Error( 'json_parse_failed', 'AI 回傳內容無法解析為 JSON（retry 後仍失敗）' );
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Wrova_Image_Analyzer {

	private Wrova_AI_Provider_Service $ai;

	public function __construct() {
		$this->ai = new Wrova_AI_Provider_Service();
	}

	public function analyze( array $image_ids ): array {
		$results = [];

		$prompt = '請分析這張照片，以 JSON 格式回傳（不要加任何說明）：
{
  "keywords": ["最多10個描述照片內容的繁體中文關鍵字"],
  "mood": "照片傳達的情緒或氛圍（1-4字）",
  "description": "一句話描述照片內容（繁體中文）"
}';

		foreach ( $image_ids as $id ) {
			$image_data = $this->get_image_data( (int) $id );
			if ( null === $image_data ) {
				error_log( 'Wrova_Image_Analyzer: 無法取得圖片資料，attachment_id=' . $id );
				continue;
			}

			$result = $this->ai->generate( $prompt, '', [ $image_data ] );

			if ( is_wp_error( $result ) ) {
				error_log( 'Wrova_Image_Analyzer: 分析失敗，attachment_id=' . $id . '，錯誤：' . $result->get_error_message() );
				continue;
			}

			$results[ $id ] = [
				'keywords'    => $result['keywords']    ?? [],
				'mood'        => $result['mood']        ?? '',
				'description' => $result['description'] ?? '',
			];
		}

		return $results;
	}

	private function get_image_data( int $attachment_id ): ?array {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
			return null;
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw ) {
			return null;
		}

		return [
			'mime_type' => $mime_type,
			'data'      => base64_encode( $raw ),
		];
	}

	public function merge_results( array $results ): array {
		if ( empty( $results ) ) {
			return [
				'keywords'    => [],
				'mood'        => '',
				'description' => '',
			];
		}

		$all_keywords  = [];
		$first_mood    = '';
		$descriptions  = [];

		foreach ( $results as $result ) {
			if ( ! empty( $result['keywords'] ) && is_array( $result['keywords'] ) ) {
				$all_keywords = array_merge( $all_keywords, $result['keywords'] );
			}

			if ( '' === $first_mood && ! empty( $result['mood'] ) ) {
				$first_mood = $result['mood'];
			}

			if ( ! empty( $result['description'] ) ) {
				$descriptions[] = $result['description'];
			}
		}

		return [
			'keywords'    => array_values( array_unique( $all_keywords ) ),
			'mood'        => $first_mood,
			'description' => implode( '；', $descriptions ),
		];
	}
}

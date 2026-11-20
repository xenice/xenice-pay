<?php
/**
 * 纯 PHP 二维码编码器（字节模式）
 *
 * 支持版本 1-40、纠错等级 L/M/Q/H，直接输出 SVG，不依赖 GD、Imagick 或第三方库。
 *
 * @package Xenice_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenice_Pay_QRcode {

	const ECL_L = 0;
	const ECL_M = 1;
	const ECL_Q = 2;
	const ECL_H = 3;

	/**
	 * 纠错等级对应的格式信息位（L=01, M=00, Q=11, H=10）
	 *
	 * @var int[]
	 */
	private static $ecl_format_bits = array( 1, 0, 3, 2 );

	/**
	 * 各版本、各纠错等级的纠错参数
	 *
	 * 结构：版本 => array( L, M, Q, H )，每个等级为 array( 每块纠错码字数, array( array( 块数, 每块数据码字数 ), ... ) )
	 *
	 * @var array
	 */
	private static $rs_blocks = array(
		1 => array(array(7, array(array(1, 19))), array(10, array(array(1, 16))), array(13, array(array(1, 13))), array(17, array(array(1, 9)))),
		2 => array(array(10, array(array(1, 34))), array(16, array(array(1, 28))), array(22, array(array(1, 22))), array(28, array(array(1, 16)))),
		3 => array(array(15, array(array(1, 55))), array(26, array(array(1, 44))), array(18, array(array(2, 17))), array(22, array(array(2, 13)))),
		4 => array(array(20, array(array(1, 80))), array(18, array(array(2, 32))), array(26, array(array(2, 24))), array(16, array(array(4, 9)))),
		5 => array(array(26, array(array(1, 108))), array(24, array(array(2, 43))), array(18, array(array(2, 15), array(2, 16))), array(22, array(array(2, 11), array(2, 12)))),
		6 => array(array(18, array(array(2, 68))), array(16, array(array(4, 27))), array(24, array(array(4, 19))), array(28, array(array(4, 15)))),
		7 => array(array(20, array(array(2, 78))), array(18, array(array(4, 31))), array(18, array(array(2, 14), array(4, 15))), array(26, array(array(4, 13), array(1, 14)))),
		8 => array(array(24, array(array(2, 97))), array(22, array(array(2, 38), array(2, 39))), array(22, array(array(4, 18), array(2, 19))), array(26, array(array(4, 14), array(2, 15)))),
		9 => array(array(30, array(array(2, 116))), array(22, array(array(3, 36), array(2, 37))), array(20, array(array(4, 16), array(4, 17))), array(24, array(array(4, 12), array(4, 13)))),
		10 => array(array(18, array(array(2, 68), array(2, 69))), array(26, array(array(4, 43), array(1, 44))), array(24, array(array(6, 19), array(2, 20))), array(28, array(array(6, 15), array(2, 16)))),
		11 => array(array(20, array(array(4, 81))), array(30, array(array(1, 50), array(4, 51))), array(28, array(array(4, 22), array(4, 23))), array(24, array(array(3, 12), array(8, 13)))),
		12 => array(array(24, array(array(2, 92), array(2, 93))), array(22, array(array(6, 36), array(2, 37))), array(26, array(array(4, 20), array(6, 21))), array(28, array(array(7, 14), array(4, 15)))),
		13 => array(array(26, array(array(4, 107))), array(22, array(array(8, 37), array(1, 38))), array(24, array(array(8, 20), array(4, 21))), array(22, array(array(12, 11), array(4, 12)))),
		14 => array(array(30, array(array(3, 115), array(1, 116))), array(24, array(array(4, 40), array(5, 41))), array(20, array(array(11, 16), array(5, 17))), array(24, array(array(11, 12), array(5, 13)))),
		15 => array(array(22, array(array(5, 87), array(1, 88))), array(24, array(array(5, 41), array(5, 42))), array(30, array(array(5, 24), array(7, 25))), array(24, array(array(11, 12), array(7, 13)))),
		16 => array(array(24, array(array(5, 98), array(1, 99))), array(28, array(array(7, 45), array(3, 46))), array(24, array(array(15, 19), array(2, 20))), array(30, array(array(3, 15), array(13, 16)))),
		17 => array(array(28, array(array(1, 107), array(5, 108))), array(28, array(array(10, 46), array(1, 47))), array(28, array(array(1, 22), array(15, 23))), array(28, array(array(2, 14), array(17, 15)))),
		18 => array(array(30, array(array(5, 120), array(1, 121))), array(26, array(array(9, 43), array(4, 44))), array(28, array(array(17, 22), array(1, 23))), array(28, array(array(2, 14), array(19, 15)))),
		19 => array(array(28, array(array(3, 113), array(4, 114))), array(26, array(array(3, 44), array(11, 45))), array(26, array(array(17, 21), array(4, 22))), array(26, array(array(9, 13), array(16, 14)))),
		20 => array(array(28, array(array(3, 107), array(5, 108))), array(26, array(array(3, 41), array(13, 42))), array(30, array(array(15, 24), array(5, 25))), array(28, array(array(15, 15), array(10, 16)))),
		21 => array(array(28, array(array(4, 116), array(4, 117))), array(26, array(array(17, 42))), array(28, array(array(17, 22), array(6, 23))), array(30, array(array(19, 16), array(6, 17)))),
		22 => array(array(28, array(array(2, 111), array(7, 112))), array(28, array(array(17, 46))), array(30, array(array(7, 24), array(16, 25))), array(24, array(array(34, 13)))),
		23 => array(array(30, array(array(4, 121), array(5, 122))), array(28, array(array(4, 47), array(14, 48))), array(30, array(array(11, 24), array(14, 25))), array(30, array(array(16, 15), array(14, 16)))),
		24 => array(array(30, array(array(6, 117), array(4, 118))), array(28, array(array(6, 45), array(14, 46))), array(30, array(array(11, 24), array(16, 25))), array(30, array(array(30, 16), array(2, 17)))),
		25 => array(array(26, array(array(8, 106), array(4, 107))), array(28, array(array(8, 47), array(13, 48))), array(30, array(array(7, 24), array(22, 25))), array(30, array(array(22, 15), array(13, 16)))),
		26 => array(array(28, array(array(10, 114), array(2, 115))), array(28, array(array(19, 46), array(4, 47))), array(28, array(array(28, 22), array(6, 23))), array(30, array(array(33, 16), array(4, 17)))),
		27 => array(array(30, array(array(8, 122), array(4, 123))), array(28, array(array(22, 45), array(3, 46))), array(30, array(array(8, 23), array(26, 24))), array(30, array(array(12, 15), array(28, 16)))),
		28 => array(array(30, array(array(3, 117), array(10, 118))), array(28, array(array(3, 45), array(23, 46))), array(30, array(array(4, 24), array(31, 25))), array(30, array(array(11, 15), array(31, 16)))),
		29 => array(array(30, array(array(7, 116), array(7, 117))), array(28, array(array(21, 45), array(7, 46))), array(30, array(array(1, 23), array(37, 24))), array(30, array(array(19, 15), array(26, 16)))),
		30 => array(array(30, array(array(5, 115), array(10, 116))), array(28, array(array(19, 47), array(10, 48))), array(30, array(array(15, 24), array(25, 25))), array(30, array(array(23, 15), array(25, 16)))),
		31 => array(array(30, array(array(13, 115), array(3, 116))), array(28, array(array(2, 46), array(29, 47))), array(30, array(array(42, 24), array(1, 25))), array(30, array(array(23, 15), array(28, 16)))),
		32 => array(array(30, array(array(17, 115))), array(28, array(array(10, 46), array(23, 47))), array(30, array(array(10, 24), array(35, 25))), array(30, array(array(19, 15), array(35, 16)))),
		33 => array(array(30, array(array(17, 115), array(1, 116))), array(28, array(array(14, 46), array(21, 47))), array(30, array(array(29, 24), array(19, 25))), array(30, array(array(11, 15), array(46, 16)))),
		34 => array(array(30, array(array(13, 115), array(6, 116))), array(28, array(array(14, 46), array(23, 47))), array(30, array(array(44, 24), array(7, 25))), array(30, array(array(59, 16), array(1, 17)))),
		35 => array(array(30, array(array(12, 121), array(7, 122))), array(28, array(array(12, 47), array(26, 48))), array(30, array(array(39, 24), array(14, 25))), array(30, array(array(22, 15), array(41, 16)))),
		36 => array(array(30, array(array(6, 121), array(14, 122))), array(28, array(array(6, 47), array(34, 48))), array(30, array(array(46, 24), array(10, 25))), array(30, array(array(2, 15), array(64, 16)))),
		37 => array(array(30, array(array(17, 122), array(4, 123))), array(28, array(array(29, 46), array(14, 47))), array(30, array(array(49, 24), array(10, 25))), array(30, array(array(24, 15), array(46, 16)))),
		38 => array(array(30, array(array(4, 122), array(18, 123))), array(28, array(array(13, 46), array(32, 47))), array(30, array(array(48, 24), array(14, 25))), array(30, array(array(42, 15), array(32, 16)))),
		39 => array(array(30, array(array(20, 117), array(4, 118))), array(28, array(array(40, 47), array(7, 48))), array(30, array(array(43, 24), array(22, 25))), array(30, array(array(10, 15), array(67, 16)))),
		40 => array(array(30, array(array(19, 118), array(6, 119))), array(28, array(array(18, 47), array(31, 48))), array(30, array(array(34, 24), array(34, 25))), array(30, array(array(20, 15), array(61, 16)))),
	);

	/**
	 * 生成二维码模块矩阵
	 *
	 * @param string $data 内容（按 UTF-8 字节编码）
	 * @param int    $ecl  纠错等级
	 * @param int    $mask 掩码编号 0-7，-1 表示自动选择
	 * @return array 行优先的 0/1 矩阵，不含静区
	 * @throws InvalidArgumentException 内容为空或过长时抛出
	 */
	public static function matrix( $data, $ecl = self::ECL_M, $mask = -1 ) {
		$data = (string) $data;
		if ( '' === $data ) {
			throw new InvalidArgumentException( '二维码内容为空' );
		}

		$ecl     = (int) $ecl;
		$length  = strlen( $data );
		$version = 0;

		for ( $v = 1; $v <= 40; $v++ ) {
			$need = 4 + self::char_count_bits( $v ) + $length * 8;
			if ( $need <= self::data_bits( $v, $ecl ) ) {
				$version = $v;
				break;
			}
		}
		if ( ! $version ) {
			throw new InvalidArgumentException( '内容过长，无法生成二维码' );
		}

		$capacity_bits = self::data_bits( $version, $ecl );

		$bits = array();
		self::push_bits( $bits, 0x04, 4 );
		self::push_bits( $bits, $length, self::char_count_bits( $version ) );
		for ( $i = 0; $i < $length; $i++ ) {
			self::push_bits( $bits, ord( $data[ $i ] ), 8 );
		}
		self::push_bits( $bits, 0, min( 4, $capacity_bits - count( $bits ) ) );
		while ( count( $bits ) % 8 !== 0 ) {
			$bits[] = 0;
		}

		$codewords = array();
		$total     = count( $bits );
		for ( $i = 0; $i < $total; $i += 8 ) {
			$byte = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$byte = ( $byte << 1 ) | $bits[ $i + $j ];
			}
			$codewords[] = $byte;
		}

		$pad = array( 0xEC, 0x11 );
		$k   = 0;
		$max = $capacity_bits >> 3;
		while ( count( $codewords ) < $max ) {
			$codewords[] = $pad[ $k % 2 ];
			$k++;
		}

		$interleaved = self::interleave( $codewords, $version, $ecl );
		$modules     = self::build_matrix( $interleaved, $version, $ecl, $mask );

		return $modules;
	}

	/**
	 * 生成 SVG 字符串
	 *
	 * @param string $data   内容
	 * @param int    $px     输出边长（像素）
	 * @param int    $margin 静区宽度（模块数）
	 * @param int    $ecl    纠错等级
	 * @param string $dark   深色
	 * @param string $light  浅色
	 * @return string
	 */
	public static function svg( $data, $px = 240, $margin = 4, $ecl = self::ECL_M, $dark = '#111111', $light = '#ffffff' ) {
		$matrix = self::matrix( $data, $ecl );
		$size   = count( $matrix );
		$total  = $size + $margin * 2;
		$path   = '';

		for ( $y = 0; $y < $size; $y++ ) {
			$x = 0;
			while ( $x < $size ) {
				if ( ! $matrix[ $y ][ $x ] ) {
					$x++;
					continue;
				}
				$start = $x;
				while ( $x < $size && $matrix[ $y ][ $x ] ) {
					$x++;
				}
				$path .= 'M' . ( $start + $margin ) . ' ' . ( $y + $margin ) . 'h' . ( $x - $start ) . 'v1h-' . ( $x - $start ) . 'z';
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $px . '" height="' . (int) $px . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
			. '<rect width="' . $total . '" height="' . $total . '" fill="' . esc_attr( $light ) . '"/>'
			. '<path d="' . $path . '" fill="' . esc_attr( $dark ) . '"/></svg>';
	}

	/**
	 * 数据区可容纳的位数
	 *
	 * @param int $version 版本
	 * @param int $ecl     纠错等级
	 * @return int
	 */
	private static function data_bits( $version, $ecl ) {
		$bits = 0;
		foreach ( self::$rs_blocks[ $version ][ $ecl ][1] as $def ) {
			$bits += $def[0] * $def[1] * 8;
		}
		return $bits;
	}

	/**
	 * 字节模式的字符计数位长度
	 *
	 * @param int $version 版本
	 * @return int
	 */
	private static function char_count_bits( $version ) {
		return $version <= 9 ? 8 : 16;
	}

	/**
	 * 追加定长比特
	 *
	 * @param array $bits  比特数组
	 * @param int   $value 值
	 * @param int   $count 位数
	 */
	private static function push_bits( &$bits, $value, $count ) {
		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * 分块并计算纠错码，按标准交织
	 *
	 * @param array $codewords 数据码字
	 * @param int   $version   版本
	 * @param int   $ecl       纠错等级
	 * @return array
	 */
	private static function interleave( $codewords, $version, $ecl ) {
		$ec_len = self::$rs_blocks[ $version ][ $ecl ][0];
		$defs   = self::$rs_blocks[ $version ][ $ecl ][1];
		$blocks = array();
		$offset = 0;

		foreach ( $defs as $def ) {
			for ( $b = 0; $b < $def[0]; $b++ ) {
				$chunk    = array_slice( $codewords, $offset, $def[1] );
				$offset  += $def[1];
				$blocks[] = array( $chunk, self::rs_remainder( $chunk, $ec_len ) );
			}
		}

		$out      = array();
		$max_data = 0;
		foreach ( $blocks as $block ) {
			$max_data = max( $max_data, count( $block[0] ) );
		}
		for ( $i = 0; $i < $max_data; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block[0][ $i ] ) ) {
					$out[] = $block[0][ $i ];
				}
			}
		}
		for ( $i = 0; $i < $ec_len; $i++ ) {
			foreach ( $blocks as $block ) {
				$out[] = $block[1][ $i ];
			}
		}

		return $out;
	}

	/**
	 * 计算 Reed-Solomon 纠错码
	 *
	 * @param array $data   数据码字
	 * @param int   $degree 纠错码字数
	 * @return array
	 */
	private static function rs_remainder( $data, $degree ) {
		$divisor = self::rs_divisor( $degree );
		$result  = array_fill( 0, $degree, 0 );

		foreach ( $data as $byte ) {
			$factor = $byte ^ $result[0];
			array_shift( $result );
			$result[] = 0;
			for ( $i = 0; $i < $degree; $i++ ) {
				$result[ $i ] ^= self::gf_mul( $divisor[ $i ], $factor );
			}
		}

		return $result;
	}

	/**
	 * 生成 RS 生成多项式（去掉最高次项后按升幂排列）
	 *
	 * @param int $degree 次数
	 * @return array
	 */
	private static function rs_divisor( $degree ) {
		static $cache = array();
		if ( isset( $cache[ $degree ] ) ) {
			return $cache[ $degree ];
		}

		$result                = array_fill( 0, $degree, 0 );
		$result[ $degree - 1 ] = 1;
		$root                  = 1;

		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$result[ $j ] = self::gf_mul( $result[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$result[ $j ] ^= $result[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}

		return $cache[ $degree ] = $result;
	}

	/**
	 * GF(256) 乘法，本原多项式 0x11D
	 *
	 * @param int $x 乘数
	 * @param int $y 乘数
	 * @return int
	 */
	private static function gf_mul( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z & 0xFF;
	}

	/**
	 * 构建二维码矩阵
	 *
	 * @param array $codewords 交织后的码字
	 * @param int   $version   版本
	 * @param int   $ecl       纠错等级
	 * @param int   $mask      指定掩码，-1 为自动
	 * @return array
	 */
	private static function build_matrix( $codewords, $version, $ecl, $mask ) {
		$size    = 17 + 4 * $version;
		$modules = array();
		$func    = array();

		for ( $y = 0; $y < $size; $y++ ) {
			$modules[ $y ] = array_fill( 0, $size, 0 );
			$func[ $y ]    = array_fill( 0, $size, false );
		}

		for ( $i = 0; $i < $size; $i++ ) {
			self::set_func( $modules, $func, 6, $i, 0 === $i % 2 );
			self::set_func( $modules, $func, $i, 6, 0 === $i % 2 );
		}

		self::draw_finder( $modules, $func, 3, 3 );
		self::draw_finder( $modules, $func, $size - 4, 3 );
		self::draw_finder( $modules, $func, 3, $size - 4 );

		$positions = self::alignment_positions( $version );
		$count     = count( $positions );
		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = 0; $j < $count; $j++ ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $j === $count - 1 ) || ( $i === $count - 1 && 0 === $j ) ) {
					continue;
				}
				self::draw_alignment( $modules, $func, $positions[ $i ], $positions[ $j ] );
			}
		}

		self::draw_format( $modules, $func, $ecl, 0 );
		self::draw_version( $modules, $func, $version );

		$bit_length = count( $codewords ) * 8;
		$index      = 0;
		for ( $right = $size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $vert = 0; $vert < $size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? ( $size - 1 - $vert ) : $vert;
					if ( ! $func[ $y ][ $x ] && $index < $bit_length ) {
						$modules[ $y ][ $x ] = ( $codewords[ $index >> 3 ] >> ( 7 - ( $index & 7 ) ) ) & 1;
						$index++;
					}
				}
			}
		}

		$candidates = array( 0, 1, 2, 3, 4, 5, 6, 7 );
		if ( $mask >= 0 && $mask <= 7 ) {
			$candidates = array( (int) $mask );
		}

		$best       = $modules;
		$best_score = PHP_INT_MAX;

		foreach ( $candidates as $current ) {
			$candidate = $modules;
			for ( $y = 0; $y < $size; $y++ ) {
				for ( $x = 0; $x < $size; $x++ ) {
					if ( ! $func[ $y ][ $x ] && self::mask_bit( $current, $x, $y ) ) {
						$candidate[ $y ][ $x ] ^= 1;
					}
				}
			}
			$func_copy = $func;
			self::draw_format( $candidate, $func_copy, $ecl, $current );
			$score = self::penalty( $candidate, $size );
			if ( $score < $best_score ) {
				$best_score = $score;
				$best       = $candidate;
			}
		}

		return $best;
	}

	/**
	 * 写入功能图形
	 *
	 * @param array $modules 矩阵
	 * @param array $func    功能图形标记
	 * @param int   $x       横坐标
	 * @param int   $y       纵坐标
	 * @param bool  $dark    是否深色
	 */
	private static function set_func( &$modules, &$func, $x, $y, $dark ) {
		$size = count( $modules );
		if ( $x < 0 || $y < 0 || $x >= $size || $y >= $size ) {
			return;
		}
		$modules[ $y ][ $x ] = $dark ? 1 : 0;
		$func[ $y ][ $x ]    = true;
	}

	/**
	 * 定位图形
	 *
	 * @param array $modules 矩阵
	 * @param array $func    功能图形标记
	 * @param int   $cx      中心横坐标
	 * @param int   $cy      中心纵坐标
	 */
	private static function draw_finder( &$modules, &$func, $cx, $cy ) {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$dist = max( abs( $dx ), abs( $dy ) );
				self::set_func( $modules, $func, $cx + $dx, $cy + $dy, 2 !== $dist && 4 !== $dist );
			}
		}
	}

	/**
	 * 校正图形
	 *
	 * @param array $modules 矩阵
	 * @param array $func    功能图形标记
	 * @param int   $cx      中心横坐标
	 * @param int   $cy      中心纵坐标
	 */
	private static function draw_alignment( &$modules, &$func, $cx, $cy ) {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				$dist = max( abs( $dx ), abs( $dy ) );
				self::set_func( $modules, $func, $cx + $dx, $cy + $dy, 1 !== $dist );
			}
		}
	}

	/**
	 * 校正图形位置
	 *
	 * @param int $version 版本
	 * @return int[]
	 */
	private static function alignment_positions( $version ) {
		if ( 1 === $version ) {
			return array();
		}

		$num  = intdiv( $version, 7 ) + 2;
		$size = 17 + 4 * $version;
		$step = ( 32 === $version ) ? 26 : intdiv( $version * 4 + $num * 2 + 1, $num * 2 - 2 ) * 2;

		$result = array();
		for ( $i = 0; $i < $num - 1; $i++ ) {
			$result[] = $size - 7 - $i * $step;
		}
		$result[] = 6;

		return array_reverse( $result );
	}

	/**
	 * 格式信息
	 *
	 * @param array $modules 矩阵
	 * @param array $func    功能图形标记
	 * @param int   $ecl     纠错等级
	 * @param int   $mask    掩码
	 */
	private static function draw_format( &$modules, &$func, $ecl, $mask ) {
		$size = count( $modules );
		$data = ( self::$ecl_format_bits[ $ecl ] << 3 ) | $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;

		for ( $i = 0; $i <= 5; $i++ ) {
			self::set_func( $modules, $func, 8, $i, self::bit( $bits, $i ) );
		}
		self::set_func( $modules, $func, 8, 7, self::bit( $bits, 6 ) );
		self::set_func( $modules, $func, 8, 8, self::bit( $bits, 7 ) );
		self::set_func( $modules, $func, 7, 8, self::bit( $bits, 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			self::set_func( $modules, $func, 14 - $i, 8, self::bit( $bits, $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			self::set_func( $modules, $func, $size - 1 - $i, 8, self::bit( $bits, $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			self::set_func( $modules, $func, 8, $size - 15 + $i, self::bit( $bits, $i ) );
		}
		self::set_func( $modules, $func, 8, $size - 8, true );
	}

	/**
	 * 版本信息（版本 7 起）
	 *
	 * @param array $modules 矩阵
	 * @param array $func    功能图形标记
	 * @param int   $version 版本
	 */
	private static function draw_version( &$modules, &$func, $version ) {
		if ( $version < 7 ) {
			return;
		}

		$size = count( $modules );
		$rem  = $version;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
		}
		$bits = ( $version << 12 ) | $rem;

		for ( $i = 0; $i < 18; $i++ ) {
			$bit = self::bit( $bits, $i );
			$pos = $size - 11 + $i % 3;
			$idx = intdiv( $i, 3 );
			self::set_func( $modules, $func, $pos, $idx, $bit );
			self::set_func( $modules, $func, $idx, $pos, $bit );
		}
	}

	/**
	 * 取第 i 位
	 *
	 * @param int $value 值
	 * @param int $i     位置
	 * @return bool
	 */
	private static function bit( $value, $i ) {
		return 0 !== ( ( $value >> $i ) & 1 );
	}

	/**
	 * 掩码判定
	 *
	 * @param int $mask 掩码编号
	 * @param int $x    横坐标
	 * @param int $y    纵坐标
	 * @return bool
	 */
	private static function mask_bit( $mask, $x, $y ) {
		switch ( $mask ) {
			case 0:
				return 0 === ( $x + $y ) % 2;
			case 1:
				return 0 === $y % 2;
			case 2:
				return 0 === $x % 3;
			case 3:
				return 0 === ( $x + $y ) % 3;
			case 4:
				return 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;
			case 5:
				return 0 === ( $x * $y ) % 2 + ( $x * $y ) % 3;
			case 6:
				return 0 === ( ( $x * $y ) % 2 + ( $x * $y ) % 3 ) % 2;
			case 7:
				return 0 === ( ( ( $x + $y ) % 2 ) + ( $x * $y ) % 3 ) % 2;
		}
		return false;
	}

	/**
	 * 掩码罚分
	 *
	 * @param array $modules 矩阵
	 * @param int   $size    边长
	 * @return int
	 */
	private static function penalty( $modules, $size ) {
		$result = 0;

		for ( $y = 0; $y < $size; $y++ ) {
			$run_color = false;
			$run_len   = 0;
			$history   = array( 0, 0, 0, 0, 0, 0, 0 );
			for ( $x = 0; $x < $size; $x++ ) {
				$color = (bool) $modules[ $y ][ $x ];
				if ( $color === $run_color ) {
					$run_len++;
					if ( 5 === $run_len ) {
						$result += 3;
					} elseif ( $run_len > 5 ) {
						$result++;
					}
				} else {
					self::add_history( $run_len, $history, $size );
					if ( ! $run_color ) {
						$result += self::count_patterns( $history ) * 40;
					}
					$run_color = $color;
					$run_len   = 1;
				}
			}
			$result += self::terminate_and_count( $run_color, $run_len, $history, $size ) * 40;
		}

		for ( $x = 0; $x < $size; $x++ ) {
			$run_color = false;
			$run_len   = 0;
			$history   = array( 0, 0, 0, 0, 0, 0, 0 );
			for ( $y = 0; $y < $size; $y++ ) {
				$color = (bool) $modules[ $y ][ $x ];
				if ( $color === $run_color ) {
					$run_len++;
					if ( 5 === $run_len ) {
						$result += 3;
					} elseif ( $run_len > 5 ) {
						$result++;
					}
				} else {
					self::add_history( $run_len, $history, $size );
					if ( ! $run_color ) {
						$result += self::count_patterns( $history ) * 40;
					}
					$run_color = $color;
					$run_len   = 1;
				}
			}
			$result += self::terminate_and_count( $run_color, $run_len, $history, $size ) * 40;
		}

		for ( $y = 0; $y < $size - 1; $y++ ) {
			for ( $x = 0; $x < $size - 1; $x++ ) {
				$c = $modules[ $y ][ $x ];
				if ( $c === $modules[ $y ][ $x + 1 ] && $c === $modules[ $y + 1 ][ $x ] && $c === $modules[ $y + 1 ][ $x + 1 ] ) {
					$result += 3;
				}
			}
		}

		$dark  = 0;
		$total = $size * $size;
		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				$dark += $modules[ $y ][ $x ];
			}
		}
		$k = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		if ( $k > 0 ) {
			$result += $k * 10;
		}

		return $result;
	}

	/**
	 * 记录游程长度
	 *
	 * @param int   $run_len 当前游程长度
	 * @param array $history 游程历史
	 * @param int   $size    边长
	 */
	private static function add_history( $run_len, &$history, $size ) {
		if ( 0 === $history[0] ) {
			$run_len += $size;
		}
		array_unshift( $history, $run_len );
		array_pop( $history );
	}

	/**
	 * 统计 1:1:3:1:1 并带 4 倍浅色区的组合
	 *
	 * @param array $history 游程历史
	 * @return int
	 */
	private static function count_patterns( $history ) {
		$n    = $history[1];
		$core = $n > 0 && $history[2] === $n && $history[3] === $n * 3 && $history[4] === $n && $history[5] === $n;

		if ( ! $core ) {
			return 0;
		}

		return ( $history[0] >= $n * 4 && $history[6] >= $n ? 1 : 0 )
			+ ( $history[6] >= $n * 4 && $history[0] >= $n ? 1 : 0 );
	}

	/**
	 * 收尾并统计
	 *
	 * @param bool  $run_color 末段颜色
	 * @param int   $run_len   末段长度
	 * @param array $history   游程历史
	 * @param int   $size      边长
	 * @return int
	 */
	private static function terminate_and_count( $run_color, $run_len, &$history, $size ) {
		if ( $run_color ) {
			self::add_history( $run_len, $history, $size );
			$run_len = 0;
		}
		$run_len += $size;
		self::add_history( $run_len, $history, $size );

		return self::count_patterns( $history );
	}
}

<?php
/**
 * Plugin Name: Erfan Student System
 * Description: پنل دانش‌آموز عرفان با ورود، ثبت‌نام و کلاس آنلاین BigBlueButton.
 * Version: 1.0.0
 * Author: Erfan
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

class Erfan_Student_System {
  static function init(){
    add_shortcode('erfan_login',[__CLASS__,'login']);
    add_shortcode('erfan_register',[__CLASS__,'register']);
    add_shortcode('erfan_panel',[__CLASS__,'panel']);
    add_action('init',[__CLASS__,'forms']);
    add_action('wp_enqueue_scripts',[__CLASS__,'styles']);
    add_action('template_redirect',[__CLASS__,'protect_panel']);
  }

  static function activate(){
    $pages=[
      'erfan-login'=>['ورود عرفان','[erfan_login]'],
      'erfan-register'=>['ثبت‌نام عرفان','[erfan_register]'],
      'erfan-panel'=>['پنل دانش‌آموز','[erfan_panel]']
    ];
    foreach($pages as $slug=>$p){
      if(!get_page_by_path($slug)){
        wp_insert_post([
          'post_title'=>$p[0],
          'post_name'=>$slug,
          'post_content'=>$p[1],
          'post_status'=>'publish',
          'post_type'=>'page'
        ]);
      }
    }
    flush_rewrite_rules();
  }

  static function styles(){
    wp_register_style('erfan-student-style',false,[],'1.0.0');
    wp_enqueue_style('erfan-student-style');
    wp_add_inline_style('erfan-student-style','
      .erfan-panel{direction:rtl;max-width:1100px;margin:30px auto;padding:20px;font-family:inherit}
      .erfan-hero{background:linear-gradient(135deg,#111827,#303b4f);color:#fff;border-radius:28px;padding:38px 42px;margin-bottom:28px}
      .erfan-hero small{color:#9ca3af;font-size:16px}.erfan-hero h1{font-size:42px;margin:12px 0}.erfan-hero p{color:#94a3b8;font-size:18px}
      .erfan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.erfan-card{border:1px solid #e2e6ed;border-radius:22px;padding:28px;background:#fff;min-height:130px}
      .erfan-card h3{margin:0 0 14px;font-size:21px}.erfan-card p{color:#64748b;line-height:2}
      .erfan-live{background:linear-gradient(135deg,#f8fbff,#eef6ff);border-color:#cfe0ff}
      .erfan-ready{color:#16803a!important;font-weight:700}.erfan-btn{display:inline-block;background:#2563eb;color:#fff!important;text-decoration:none!important;padding:12px 20px;border-radius:12px;font-weight:700;margin-top:5px}
      .erfan-auth{direction:rtl;max-width:500px;margin:40px auto}.erfan-auth-card{padding:30px;border:1px solid #e5e7eb;border-radius:22px;background:#fff}.erfan-auth input{width:100%;box-sizing:border-box;padding:13px;margin:7px 0 14px;border:1px solid #d1d5db;border-radius:10px}.erfan-auth button{width:100%;padding:13px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700}
      @media(max-width:800px){.erfan-grid{grid-template-columns:1fr}.erfan-hero h1{font-size:30px}}
    ');
  }

  static function page($slug){
    $p=get_page_by_path($slug);
    return $p?get_permalink($p):home_url('/'.$slug.'/');
  }

  static function protect_panel(){
    if(is_page('erfan-panel') && !is_user_logged_in()){
      wp_safe_redirect(self::page('erfan-login')); exit;
    }
  }

  static function forms(){
    if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_POST['erfan_action'])) return;
    if(empty($_POST['erfan_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['erfan_nonce'])),'erfan_forms')) return;
    $action=sanitize_key(wp_unslash($_POST['erfan_action']));

    if($action==='login'){
      $user=wp_signon([
        'user_login'=>sanitize_text_field(wp_unslash($_POST['username']??'')),
        'user_password'=>(string)($_POST['password']??''),
        'remember'=>true
      ],is_ssl());
      if(!is_wp_error($user)){wp_safe_redirect(self::page('erfan-panel'));exit;}
    }

    if($action==='register'){
      $username=sanitize_user(wp_unslash($_POST['username']??''));
      $email=sanitize_email(wp_unslash($_POST['email']??''));
      $password=(string)($_POST['password']??'');
      if(!$username||!$email||!$password||username_exists($username)||email_exists($email)) return;
      $id=wp_create_user($username,$password,$email);
      if(!is_wp_error($id)){
        wp_update_user(['ID'=>$id,'role'=>'subscriber']);
        wp_set_auth_cookie($id,true);
        wp_safe_redirect(self::page('erfan-panel'));exit;
      }
    }
  }

  /*
   * برای اتصال به BiggerBlueButton، اتصال فعلی سایت می‌تواند
   * لینک Join امضاشده را از سمت سرور برگرداند:
   *
   * add_filter('erfan_student_bbb_join_url', function($url,$user){
   *   return $signed_join_url;
   * },10,2);
   *
   * Secret هرگز نباید در JavaScript یا HTML قرار بگیرد.
   */
  static function bbb_url(){
    if(!is_user_logged_in()) return '';
    $url=apply_filters('erfan_student_bbb_join_url','',wp_get_current_user());
    return is_string($url)?esc_url_raw($url):'';
  }

  static function login(){
    if(is_user_logged_in()) return '<div class="erfan-auth-card">شما وارد شده‌اید. <a href="'.esc_url(self::page('erfan-panel')).'">پنل من</a></div>';
    ob_start(); ?>
    <div class="erfan-auth"><div class="erfan-auth-card">
      <h1>🎓 ورود به عرفان</h1>
      <form method="post">
        <?php wp_nonce_field('erfan_forms','erfan_nonce'); ?>
        <input type="hidden" name="erfan_action" value="login">
        <input name="username" placeholder="نام کاربری یا ایمیل" required>
        <input type="password" name="password" placeholder="رمز عبور" required>
        <button type="submit">ورود به پنل</button>
      </form>
      <p>حساب ندارید؟ <a href="<?php echo esc_url(self::page('erfan-register')); ?>">ثبت‌نام</a></p>
    </div></div>
    <?php return ob_get_clean();
  }

  static function register(){
    if(is_user_logged_in()) return '<div class="erfan-auth-card">شما وارد شده‌اید.</div>';
    ob_start(); ?>
    <div class="erfan-auth"><div class="erfan-auth-card">
      <h1>✨ ثبت‌نام دانش‌آموز</h1>
      <form method="post">
        <?php wp_nonce_field('erfan_forms','erfan_nonce'); ?>
        <input type="hidden" name="erfan_action" value="register">
        <input name="username" placeholder="نام کاربری" required>
        <input type="email" name="email" placeholder="ایمیل" required>
        <input type="password" name="password" placeholder="رمز عبور" required>
        <button type="submit">ساخت حساب</button>
      </form>
      <p>حساب دارید؟ <a href="<?php echo esc_url(self::page('erfan-login')); ?>">ورود</a></p>
    </div></div>
    <?php return ob_get_clean();
  }

  static function panel(){
    if(!is_user_logged_in()) return '';
    $user=wp_get_current_user();
    $bbb=self::bbb_url();
    ob_start(); ?>
    <div class="erfan-panel">
      <div class="erfan-hero">
        <small>خوش آمدی</small>
        <h1>👋 <?php echo esc_html($user->display_name ?: $user->user_login); ?></h1>
        <p>از اینجا دوره‌ها و کلاس‌های خودت را مدیریت کن.</p>
      </div>
      <div class="erfan-grid">
        <div class="erfan-card">
          <h3>📚 دوره‌های من</h3>
          <p>فعلاً دوره‌ای به این حساب اختصاص داده نشده است.</p>
        </div>
        <div class="erfan-card erfan-live">
          <h3>🎥 کلاس آنلاین</h3>
          <?php if($bbb): ?>
            <p class="erfan-ready">🟢 کلاس آماده ورود است</p>
            <a class="erfan-btn" href="<?php echo esc_url($bbb); ?>" target="_blank" rel="noopener noreferrer">🎥 ورود به کلاس</a>
          <?php else: ?>
            <p>پس از اختصاص کلاس، لینک ورود به BigBlueButton اینجا نمایش داده می‌شود.</p>
          <?php endif; ?>
        </div>
        <div class="erfan-card">
          <h3>🏆 مسابقات KidCode</h3>
          <p>چالش‌ها و مسابقات آینده اینجا قرار می‌گیرند.</p>
        </div>
      </div>
      <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">خروج از حساب</a></p>
    </div>
    <?php return ob_get_clean();
  }
}
Erfan_Student_System::init();
register_activation_hook(__FILE__,['Erfan_Student_System','activate']);

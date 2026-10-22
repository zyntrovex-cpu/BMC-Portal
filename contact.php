<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle  = 'Contact Us';
$activePage = 'contact';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$name || !$email || !$message) {
        $err = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        try {
            siteDB()->prepare('INSERT INTO site_contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)')
                ->execute([$name, $email, $phone, $subject, $message]);
            $msg = 'Thank you for your message! We will get back to you within 2 business days.';
        } catch (Exception $e) {
            $err = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-hero">
  <div class="container-xl position-relative" style="z-index:1">
    <div class="page-hero-label">Get In Touch</div>
    <h1 class="page-hero-title">Contact Us</h1>
    <p class="page-hero-subtitle">We're here to answer your questions</p>
  </div>
</div>
<div class="breadcrumb-wrap">
  <div class="container-xl"><nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/index.php">Home</a></li>
    <li class="breadcrumb-item active">Contact</li>
  </ol></nav></div>
</div>

<section class="site-section">
  <div class="container-xl">
    <?php if ($msg): ?><div class="alert alert-success mb-4"><?= sh($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger mb-4"><?= sh($err) ?></div><?php endif; ?>
    <div class="row g-5">
      <!-- Contact Info -->
      <div class="col-lg-4" data-aos="fade-right">
        <div class="contact-info-card mb-4">
          <h4 style="font-weight:800;color:#fff;margin-bottom:24px">Contact Information</h4>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div><div style="font-weight:700;margin-bottom:4px">Address</div><div style="opacity:.8;font-size:.9rem;line-height:1.6"><?= sh(getSetting('site_address')) ?></div></div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-phone-alt"></i></div>
            <div><div style="font-weight:700;margin-bottom:4px">Phone</div><div style="opacity:.8;font-size:.9rem"><a href="tel:<?=sh(getSetting('site_phone'))?>" style="color:inherit"><?= sh(getSetting('site_phone')) ?></a></div></div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
            <div><div style="font-weight:700;margin-bottom:4px">Email</div><div style="opacity:.8;font-size:.9rem"><a href="mailto:<?=sh(getSetting('site_email'))?>" style="color:inherit"><?= sh(getSetting('site_email')) ?></a></div></div>
          </div>
          <div class="contact-info-item" style="margin-bottom:0">
            <div class="contact-info-icon"><i class="fas fa-clock"></i></div>
            <div><div style="font-weight:700;margin-bottom:4px">Office Hours</div><div style="opacity:.8;font-size:.86rem">Mon – Fri: 8:00 AM – 3:00 PM<br>Saturday: 8:00 AM – 12:00 PM</div></div>
          </div>
        </div>
        <!-- Department Contacts -->
        <div class="card-glass p-4">
          <h6 style="font-weight:700;color:var(--primary);margin-bottom:12px"><i class="fas fa-phone-square me-2"></i>Department Contacts</h6>
          <?php foreach ([['Admissions Office','admissions@bmc.edu.pk'],['Examination Cell','exam@bmc.edu.pk'],['Accounts / Finance','finance@bmc.edu.pk'],['Principal Office','principal@bmc.edu.pk'],['Library','library@bmc.edu.pk']] as $d): ?>
          <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:.83rem">
            <span style="color:var(--text-2)"><?= $d[0] ?></span>
            <a href="mailto:<?= $d[1] ?>" style="color:var(--secondary)"><?= $d[1] ?></a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Contact Form -->
      <div class="col-lg-8" data-aos="fade-left">
        <div class="contact-form-card">
          <h3 style="font-weight:800;color:var(--primary);margin-bottom:6px">Send Us a Message</h3>
          <p style="color:var(--text-2);margin-bottom:28px;font-size:.9rem">Have a question about admissions, academics, or general enquiries? Fill out the form below and our team will respond within 2 working days.</p>
          <form method="POST" class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control-custom" required value="<?= sh($_POST['name']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control-custom" required value="<?= sh($_POST['email']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control-custom" value="<?= sh($_POST['phone']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Subject</label>
              <select name="subject" class="form-control-custom">
                <option value="">Select a topic…</option>
                <?php foreach (['Admission Enquiry','Academic Programme Information','Fee & Financial Aid','General Enquiry','Complaint / Feedback','Employment Opportunity','Other'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($_POST['subject']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Message <span class="text-danger">*</span></label>
              <textarea name="message" class="form-control-custom" rows="5" required placeholder="Write your message here…"><?= sh($_POST['message']??'') ?></textarea>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-primary-custom" style="text-decoration:none">
                <i class="fas fa-paper-plane me-2"></i>Send Message
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Map -->
<section class="site-section sec-alt py-sm">
  <div class="container-xl" data-aos="fade-up">
    <h3 class="sec-title text-center mb-4">Find Us on the Map</h3>
    <?php $mapEmbed = getSetting('site_map_embed'); ?>
    <?php if ($mapEmbed): ?>
    <div class="map-wrap"><iframe src="<?= sh($mapEmbed) ?>" width="100%" height="420" style="border:0" allowfullscreen="" loading="lazy"></iframe></div>
    <?php else: ?>
    <div class="map-wrap" style="height:400px;background:var(--light-2);display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-3);border-radius:var(--radius-xl)">
      <i class="fas fa-map-marked-alt fa-3x mb-3" style="opacity:.3"></i>
      <p>Bahria Model College Bin Qasim, Karachi</p>
      <a href="https://maps.google.com/?q=Bahria+Model+College+Bin+Qasim+Karachi" target="_blank" class="btn-primary-custom mt-2" style="text-decoration:none">Open in Google Maps <i class="fas fa-external-link-alt ms-1"></i></a>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 (engagement and site content).
 *
 *  contact_messages / contact_replies   what the Contact page form sends, and what staff answer
 *  subscribers / newsletters            "Get new papers first" sign-ups, and the emails sent to them
 *  testimonials                         the "What teachers say" row on the home page
 *  faqs                                 the question lists on the Contact page and the Holiday packages page
 *
 * The ten testimonials and ten questions that were typed into the pages are copied in here, so the website
 * looks exactly the same after the upgrade and staff can start editing them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 12)->nullable()->unique();                  // M-1001, filled right after the insert
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30)->nullable();
            $table->string('topic', 40);
            $table->text('body');
            $table->string('status', 12)->default('new');                    // new | read | replied | quoted | archived
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('email');
        });

        Schema::create('contact_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name', 120)->nullable();
            $table->string('kind', 12)->default('reply');                    // reply | quotation
            $table->string('subject', 190)->nullable();
            $table->text('body');
            $table->json('meta')->nullable();                                // quotation lines and totals
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('source', 30)->default('Footer');                  // Home page | Footer | Checkout | Contact page | Added by staff
            $table->string('status', 14)->default('active');                 // active | unsubscribed
            $table->string('token', 40)->unique();                           // the unsubscribe link
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'subscribed_at']);
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 190);
            $table->text('body');
            $table->string('audience', 10)->default('all');                  // all | schools | teachers
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name', 120)->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('stars')->default(5);
            $table->text('quote');
            $table->string('initials', 4);
            $table->string('role', 120)->nullable();
            $table->string('place', 160)->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('permission')->default(false);                   // staff confirmed the person agreed to be quoted
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['visible', 'sort_order']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('page', 10);                                      // contact | holiday
            $table->string('question', 255);
            $table->text('answer');
            $table->string('icon', 20)->default('help');
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['page', 'visible', 'sort_order']);
        });

        $seed = json_decode(<<<'JSON'
{"faqs": [{"page": "contact", "icon": "card", "question": "How do I pay?", "answer": "Pay with MTN Mobile Money, Airtel Money, Visa or Mastercard. You can also pay in cash when you collect from our shop.", "sort": 1}, {"page": "contact", "icon": "download", "question": "How fast do PDF downloads arrive?", "answer": "PDFs are emailed as soon as your payment is confirmed.", "sort": 2}, {"page": "contact", "icon": "truck", "question": "Do you deliver outside Kampala?", "answer": "Yes. Choose upcountry delivery at checkout and we will send your order by courier.", "sort": 3}, {"page": "contact", "icon": "box", "question": "Can my school get a bulk price?", "answer": "Yes. Send us a message with the titles and quantities and we will send a quotation.", "sort": 4}, {"page": "contact", "icon": "store", "question": "Can I collect my order from the shop?", "answer": "Yes. Pickup is free. Choose pickup at checkout and collect from us during opening hours.", "sort": 5}, {"page": "contact", "icon": "clock", "question": "When are you open?", "answer": "Monday to Saturday, 8am to 6pm. Messages sent outside these hours are answered the next working day.", "sort": 6}, {"page": "holiday", "icon": "calendar", "question": "When should I order a holiday package?", "answer": "Order at least one week before school closes so your package is ready for collection or delivery on the first day of the holiday.", "sort": 1}, {"page": "holiday", "icon": "refresh", "question": "Can I swap a book in the package?", "answer": "Yes. Tell us at checkout or call us, and we will swap a title for another of the same level.", "sort": 2}, {"page": "holiday", "icon": "check", "question": "Do packages include answers?", "answer": "Every test and mock paper in a package comes with a marking guide for the parent or teacher.", "sort": 3}, {"page": "holiday", "icon": "store", "question": "Can I collect my package from the shop?", "answer": "Yes. Pickup is free. Choose pickup at checkout and collect from us during opening hours.", "sort": 4}], "testimonials": [{"stars": 5, "quote": "The practical workbooks match what the learners meet in the lab. Our Senior 4 candidates walked into the exam knowing what to expect.", "initials": "HS", "role": "Head of Science", "place": "Secondary school, Wakiso", "sort": 1}, {"stars": 5, "quote": "We order the mock series for the whole Primary Seven class. The marking guides save our teachers a full weekend.", "initials": "DH", "role": "Deputy Head Teacher", "place": "Primary school, Kampala", "sort": 2}, {"stars": 5, "quote": "Buying a past paper as a PDF and paying with mobile money took minutes. Printing it for revision day was easy.", "initials": "BT", "role": "Biology teacher", "place": "Secondary school, Entebbe", "sort": 3}, {"stars": 5, "quote": "The full exam sets give us one clean series to run across every stream. Candidates sit the same paper under the same conditions.", "initials": "DS", "role": "Director of Studies", "place": "Secondary school, Mukono", "sort": 4}, {"stars": 5, "quote": "The five-year paper bundle is the fastest way I have found to drill past questions. My Senior 4 class improved noticeably.", "initials": "MT", "role": "Mathematics teacher", "place": "Secondary school, Kira", "sort": 5}, {"stars": 5, "quote": "Prices that a small school can afford, and papers that look like the real examination. Parents notice the difference.", "initials": "HT", "role": "Head Teacher", "place": "Primary school, Nansana", "sort": 6}, {"stars": 5, "quote": "Clear diagrams, sensible mark allocations and answers I can trust. I now recommend the practical workbooks to colleagues.", "initials": "CT", "role": "Chemistry teacher", "place": "Secondary school, Makindye", "sort": 7}, {"stars": 5, "quote": "The holiday package kept my learners practising while school was closed. They came back sharper and more confident.", "initials": "PT", "role": "Primary Seven class teacher", "place": "Primary school, Kawempe", "sort": 8}, {"stars": 5, "quote": "Fast delivery and well-printed copies. The sets arrived labelled by subject, so distributing them took no time at all.", "initials": "LT", "role": "Literature teacher", "place": "Secondary school, Rubaga", "sort": 9}, {"stars": 5, "quote": "Ordering for the whole school is simple. We send the list, get a quotation the same day, and pay with mobile money.", "initials": "SB", "role": "School bursar", "place": "Secondary school, Wakiso", "sort": 10}]}
JSON, true);

        $now = now();
        foreach ($seed['testimonials'] as $t) {
            DB::table('testimonials')->insert([
                'stars' => $t['stars'], 'quote' => $t['quote'], 'initials' => $t['initials'], 'role' => $t['role'], 'place' => $t['place'],
                'visible' => true, 'permission' => false, 'sort_order' => $t['sort'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ($seed['faqs'] as $f) {
            DB::table('faqs')->insert([
                'page' => $f['page'], 'question' => $f['question'], 'answer' => $f['answer'], 'icon' => $f['icon'],
                'visible' => true, 'sort_order' => $f['sort'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('contact_replies');
        Schema::dropIfExists('contact_messages');
    }
};

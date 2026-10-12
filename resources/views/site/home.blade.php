@extends('layouts.site')

@section('title')WAKATA Stationers | Books, past papers and mock examinations@endsection
@section('page')home@endsection

@section('content')

    <section class="hero">
      <div class="hero-bg" aria-hidden="true">
        <svg viewBox="0 0 1440 760" preserveAspectRatio="xMaxYMid slice" focusable="false">
          <defs>
            <linearGradient id="hp" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#fff" stop-opacity=".18" />
              <stop offset="1" stop-color="#fff" stop-opacity=".02" />
            </linearGradient>
          </defs>
          <g class="plane2">
            <polygon points="520,310 740,272 740,430 930,392 930,720 520,776" fill="#fff" fill-opacity=".04" />
          </g>
          <g class="plane">
            <polygon points="860,120 1120,76 1120,282 1388,226 1388,664 860,740" fill="url(#hp)" />
            <polyline points="1152,56 1152,304" fill="none" stroke="#fff" stroke-opacity=".3" stroke-width="2" />
            <polyline points="1422,258 1422,694 884,780" fill="none" stroke="#fff" stroke-opacity=".24"
              stroke-width="2" />
          </g>
        </svg>
        <span class="dots"></span><span class="glow"></span>
      </div>
      <div class="wrap">
        <div>
          <h1>Quality assessment, priced for every school.</h1>
          <p class="lead">Mock examinations, past papers, practical workbooks and holiday packages, written and marked
            by teachers of the Wakiso Kampala Teachers' Association.</p>
          <div class="cta"><a class="btn btn-hl" href="{{ route('site.books') }}">Shop books</a><a class="btn btn-ghost-w"
              href="{{ route('site.papers') }}">Find a past paper</a></div>
          <ul class="facts">
            <li><svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>Set by classroom teachers</li>
            <li><svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>Pay with MTN or Airtel Money</li>
            <li><svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <polyline points="20 6 9 17 4 12" />
              </svg>Printed copies or PDF</li>
          </ul>
        </div>
        <div class="fan" aria-hidden="true">
          <figure class="f1"><img src="{{ asset('assets/img/papers/maths-p1-2026.jpg') }}" alt=""></figure>
          <figure class="f2"><img src="{{ asset('assets/img/books/biology-uce.jpg') }}" alt=""></figure>
          <figure class="f3"><img src="{{ asset('assets/img/books/physics-uclse.jpg') }}" alt=""></figure>
          <figure class="f4"><img src="{{ asset('assets/img/papers/ple-maths-2025.jpg') }}" alt=""></figure>
          <span class="tick"><svg viewBox="0 0 24 24">
              <polyline points="4 13 10 19 21 6" />
            </svg></span>
          <span class="tag t1">Printed or PDF</span>
        </div>
      </div>
    </section>

    <section class="stats" aria-label="WAKATA at a glance">
      <div class="wrap">
        <div class="stats-card">
          <div class="stat"><b data-count="books">23</b><span>Books and workbooks</span></div>
          <div class="stat"><b data-count="papers">129</b><span>Past papers</span></div>
          <div class="stat"><b data-count="sets">6</b><span>Complete exam sets</span></div>
          <div class="stat"><b data-count="3">3</b><span>Levels covered: PLE, UCE, UACE</span></div>
        </div>
      </div>
    </section>

    <section class="sec sec-white">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>Textbooks and practical workbooks</h2>
            <p>Biology, Chemistry, Physics, Agriculture and more, with new curriculum editions.</p>
          </div><a class="link-more" href="{{ route('site.books') }}">See all books <svg class="ico" viewBox="0 0 24 24"
              aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="13 6 19 12 13 18" />
            </svg></a>
        </div>
        <div class="chips chips-one" id="home-chips" style="margin-bottom:1.4rem"></div>
        <div class="grid grid-books grid-4" id="home-books"></div>
      </div>
    </section>

    <section class="sec sec-blue">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>Find the exact paper you need</h2>
            <p>Choose a level, subject and year. Buy a single printed paper, or download a PDF in minutes.</p>
          </div>
        </div>
        <form class="finder" action="{{ route('site.papers') }}" method="get">
          <div class="field"><label for="f-level">Level</label><select id="f-level" name="level">
              <option value="">All levels</option>
              <option value="ple">Primary (PLE)</option>
              <option value="uce">O-Level (UCE)</option>
              <option value="uace">A-Level (UACE)</option>
            </select></div>
          <div class="field"><label for="f-subject">Subject</label><select id="f-subject" name="subject">
              <option value="">All subjects</option>
            </select></div>
          <div class="field"><label for="f-year">Year</label><select id="f-year" name="year">
              <option value="">Any year</option>
            </select></div>
          <button class="btn btn-hl" type="submit">Search papers</button>
        </form>
        <div class="sec-head" style="margin-top:2.2rem">
          <div>
            <h3 style="font-size:1.5rem">Recent papers</h3>
          </div><a class="link-more" style="color:#fff" href="{{ route('site.papers') }}">Browse all past papers <svg class="ico"
              viewBox="0 0 24 24" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="13 6 19 12 13 18" />
            </svg></a>
        </div>
        <div class="grid grid-papers" id="home-papers"></div>
      </div>
    </section>

        <section class="sec">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>Start with your level</h2>
            <p>Everything in the shop is grouped by the examination your learners are preparing for.</p>
          </div>
        </div>
        <div class="levels">
          <div class="lvl"><span class="code">PLE</span>
            <h3>Primary Seven</h3>
            <p data-lvl-count="ple:primary"></p>
            <div class="links"><a href="{{ route('site.books') }}?level=primary">Books</a><a href="{{ route('site.papers') }}?level=ple">Past
                papers</a></div>
          </div>
          <div class="lvl"><span class="code">UCE</span>
            <h3>O-Level, Senior 1 to 4</h3>
            <p data-lvl-count="uce:o"></p>
            <div class="links"><a href="{{ route('site.books') }}?level=o">Books</a><a href="{{ route('site.papers') }}?level=uce">Past
                papers</a></div>
          </div>
          <div class="lvl"><span class="code">UACE</span>
            <h3>A-Level, Senior 5 and 6</h3>
            <p data-lvl-count="uace:a"></p>
            <div class="links"><a href="{{ route('site.books') }}?level=a">Books</a><a href="{{ route('site.papers') }}?level=uace">Past
                papers</a></div>
          </div>
        </div>
      </div>
    </section>

    <section class="sec">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>Holiday packages</h2>
            <p>A ready-made bundle of workbooks, tests and mock papers, so learners keep working while school is out.
            </p>
          </div><a class="link-more" href="{{ route('site.packages') }}">All packages <svg class="ico" viewBox="0 0 24 24"
              aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="13 6 19 12 13 18" />
            </svg></a>
        </div>
        <div class="grid grid-pk" id="home-packs"></div>
      </div>
    </section>

    <section class="sec sec-white">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>What teachers say</h2>
            <p>Schools use WAKATA papers and workbooks every term.</p>
          </div><button class="mq-toggle" id="mq-toggle" type="button" aria-pressed="false" aria-controls="testimonials"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><line x1="9" y1="5" x2="9" y2="19"/><line x1="15" y1="5" x2="15" y2="19"/></svg><span>Pause</span></button>
        </div>
      </div>
        <div class="mq" id="testimonials" role="region" aria-label="Teacher testimonials, scrolling automatically">
          <div class="mq-track">
            <div class="mq-group">
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>The practical workbooks match what the learners meet in the lab. Our Senior 4 candidates walked into the exam knowing what to expect.</blockquote>
              <figcaption><span class="av" aria-hidden="true">HS</span><span><b>Head of Science</b>Secondary school, Wakiso</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>We order the mock series for the whole Primary Seven class. The marking guides save our teachers a full weekend.</blockquote>
              <figcaption><span class="av" aria-hidden="true">DH</span><span><b>Deputy Head Teacher</b>Primary school, Kampala</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>Buying a past paper as a PDF and paying with mobile money took minutes. Printing it for revision day was easy.</blockquote>
              <figcaption><span class="av" aria-hidden="true">BT</span><span><b>Biology teacher</b>Secondary school, Entebbe</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>The full exam sets give us one clean series to run across every stream. Candidates sit the same paper under the same conditions.</blockquote>
              <figcaption><span class="av" aria-hidden="true">DS</span><span><b>Director of Studies</b>Secondary school, Mukono</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>The five-year paper bundle is the fastest way I have found to drill past questions. My Senior 4 class improved noticeably.</blockquote>
              <figcaption><span class="av" aria-hidden="true">MT</span><span><b>Mathematics teacher</b>Secondary school, Kira</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>Prices that a small school can afford, and papers that look like the real examination. Parents notice the difference.</blockquote>
              <figcaption><span class="av" aria-hidden="true">HT</span><span><b>Head Teacher</b>Primary school, Nansana</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>Clear diagrams, sensible mark allocations and answers I can trust. I now recommend the practical workbooks to colleagues.</blockquote>
              <figcaption><span class="av" aria-hidden="true">CT</span><span><b>Chemistry teacher</b>Secondary school, Makindye</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>The holiday package kept my learners practising while school was closed. They came back sharper and more confident.</blockquote>
              <figcaption><span class="av" aria-hidden="true">PT</span><span><b>Primary Seven class teacher</b>Primary school, Kawempe</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>Fast delivery and well-printed copies. The sets arrived labelled by subject, so distributing them took no time at all.</blockquote>
              <figcaption><span class="av" aria-hidden="true">LT</span><span><b>Literature teacher</b>Secondary school, Rubaga</span></figcaption>
            </figure>
            <figure class="quote">
              <div class="stars" aria-label="5 out of 5">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
              <blockquote>Ordering for the whole school is simple. We send the list, get a quotation the same day, and pay with mobile money.</blockquote>
              <figcaption><span class="av" aria-hidden="true">SB</span><span><b>School bursar</b>Secondary school, Wakiso</span></figcaption>
            </figure>
            </div>
          </div>
        </div>
      </section>
  @endsection

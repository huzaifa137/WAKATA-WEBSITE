@extends('layouts.site')

@section('title', \App\Support\SiteContent::seo('home', 'title'))
@section('description', \App\Support\SiteContent::seo('home', 'desc'))
@section('page', 'home')

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
          <h1>@c('home.title')</h1>
          <p class="lead">@c('home.lead')</p>
          <div class="cta"><a class="btn btn-hl" href="{{ \App\Support\SiteContent::link('home.btn1_link') }}">@c('home.btn1')</a><a class="btn btn-ghost-w"
              href="{{ \App\Support\SiteContent::link('home.btn2_link') }}">@c('home.btn2')</a></div>
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
          <span class="tag t1">@c('home.tag')</span>
        </div>
      </div>
    </section>

    <section class="stats" aria-label="WAKATA at a glance">
      <div class="wrap">
        <div class="stats-card">
          <div class="stat"><b data-count="books">23</b><span>@c('home.stat1')</span></div>
          <div class="stat"><b data-count="papers">129</b><span>@c('home.stat2')</span></div>
          <div class="stat"><b data-count="sets">6</b><span>@c('home.stat3')</span></div>
          <div class="stat"><b data-count="3">3</b><span>@c('home.stat4')</span></div>
        </div>
      </div>
    </section>

    <section class="sec sec-white">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>@c('home.books_title')</h2>
            <p>@c('home.books_sub')</p>
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
            <h2>@c('home.papers_title')</h2>
            <p>@c('home.papers_sub')</p>
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
            <h2>@c('home.levels_title')</h2>
            <p>@c('home.levels_sub')</p>
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
            <h2>@c('home.packages_title')</h2>
            <p>@c('home.packages_sub')</p>
          </div><a class="link-more" href="{{ route('site.packages') }}">All packages <svg class="ico" viewBox="0 0 24 24"
              aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12" />
              <polyline points="13 6 19 12 13 18" />
            </svg></a>
        </div>
        <div class="grid grid-pk" id="home-packs"></div>
      </div>
    </section>

    @if ($testimonials->isNotEmpty())
    <section class="sec sec-white">
      <div class="wrap">
        <div class="sec-head">
          <div>
            <h2>@c('home.testimonials_title')</h2>
            <p>@c('home.testimonials_sub')</p>
          </div><button class="mq-toggle" id="mq-toggle" type="button" aria-pressed="false" aria-controls="testimonials"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><line x1="9" y1="5" x2="9" y2="19"/><line x1="15" y1="5" x2="15" y2="19"/></svg><span>Pause</span></button>
        </div>
      </div>
        <div class="mq" id="testimonials" role="region" aria-label="Teacher testimonials, scrolling automatically">
          <div class="mq-track">
            <div class="mq-group">
            @foreach ($testimonials as $t)
            <figure class="quote">
              <div class="stars" aria-label="{{ $t->stars }} out of 5">{!! str_repeat('&#9733;', $t->stars) . str_repeat('&#9734;', 5 - $t->stars) !!}</div>
              <blockquote>{{ $t->quote }}</blockquote>
              <figcaption><span class="av" aria-hidden="true">{{ $t->initials }}</span><span><b>{{ $t->role }}</b>{{ $t->place }}</span></figcaption>
            </figure>
            @endforeach
            </div>
          </div>
        </div>
      </section>
    @endif
  @endsection

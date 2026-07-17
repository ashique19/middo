<x-layouts.public.app>
    <section class="py-16 md:py-20 bg-[#F7F4EB]">
        <div class="max-w-3xl mx-auto px-6">
            <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange mb-3">Legal</p>
            <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#2B1A11] mb-8">{{ $page->title }}</h1>
            <article class="prose prose-stone max-w-none
                prose-headings:font-black prose-headings:text-[#2B1A11]
                prose-p:text-[#635347] prose-li:text-[#635347]
                prose-a:text-middo-orange
                bg-white border border-[#EBE3D3] rounded-2xl shadow-sm p-6 md:p-10">
                {!! $page->body !!}
            </article>
        </div>
    </section>
</x-layouts.public.app>

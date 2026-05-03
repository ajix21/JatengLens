<div class="max-w-3xl" x-data="postForm()">
<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="space-y-5">

        {{-- Akun --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-user text-indigo-500"></i> Informasi Akun
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Akun Sosial Media <span class="text-red-500">*</span></label>
                    <select name="social_account_id" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Pilih akun --</option>
                        @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}"
                                {{ old('social_account_id', $post?->social_account_id) == $acc->id ? 'selected' : '' }}>
                            {{ $acc->display_name }} (@{{ $acc->username }}) · {{ ucfirst($acc->platform) }}
                        </option>
                        @endforeach
                    </select>
                    @error('social_account_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Postingan</label>
                    <input type="url" name="post_url" value="{{ old('post_url', $post?->post_url) }}"
                           placeholder="https://..."
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('post_url')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Media</label>
                    <select name="media_type"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Tidak ditentukan</option>
                        @foreach(['text'=>'Teks','image'=>'Gambar','video'=>'Video','reel'=>'Reel','story'=>'Story'] as $v => $l)
                        <option value="{{ $v }}" {{ old('media_type', $post?->media_type) === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Konten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-align-left text-indigo-500"></i> Konten Postingan
                </h3>
                <button type="button" @click="detectKeywords"
                        class="flex items-center gap-1.5 text-xs bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-lg transition">
                    <i class="fas fa-magic"></i> Deteksi Keyword Otomatis
                </button>
            </div>
            <textarea name="content" rows="6" required x-model="content"
                      placeholder="Masukkan isi teks postingan…"
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 resize-none">{{ old('content', $post?->content) }}</textarea>
            @error('content')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

            {{-- Keyword detection result --}}
            <div x-show="detectedKeywords.length > 0" x-cloak class="mt-3 p-3 bg-purple-50 rounded-lg border border-purple-100">
                <p class="text-xs font-semibold text-purple-700 mb-2">Keyword terdeteksi:</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="kw in detectedKeywords" :key="kw.id">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium text-white"
                              :style="'background:' + kw.color">
                            <span x-text="kw.word"></span>
                            <span class="opacity-75" x-text="'×' + kw.occurrence"></span>
                        </span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Engagement --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-indigo-500"></i> Engagement
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([['likes_count','Likes','fa-heart','red'],['comments_count','Comments','fa-comment','blue'],['shares_count','Shares','fa-share','green'],['views_count','Views','fa-eye','gray']] as [$field,$label,$icon,$color])
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        <i class="fas {{ $icon }} text-{{ $color }}-400 mr-1"></i>{{ $label }}
                    </label>
                    <input type="number" name="{{ $field }}" min="0"
                           value="{{ old($field, $post?->$field ?? 0) }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Metadata --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-indigo-500"></i> Metadata
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal & Waktu Post <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="posted_at" required
                           value="{{ old('posted_at', $post?->posted_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('posted_at')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tag Postingan</label>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @foreach($allTags as $tag)
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" name="tags[]" value="{{ $tag->name }}"
                                   {{ in_array($tag->name, old('tags', $post?->tags->pluck('name')->toArray() ?? [])) ? 'checked' : '' }}
                                   class="rounded text-indigo-600">
                            <span class="text-xs px-1.5 py-0.5 rounded-full text-white" style="background:{{ $tag->color }}">{{ $tag->name }}</span>
                        </label>
                        @endforeach
                    </div>
                    <input type="text" placeholder="Tambah tag baru (Enter)"
                           @keydown.enter.prevent="addTag"
                           x-model="newTag"
                           class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        {{-- Flag --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-flag text-red-500"></i> Flag / Tandai
            </h3>
            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_flagged" value="1"
                           {{ old('is_flagged', $post?->is_flagged) ? 'checked' : '' }}
                           x-model="isFlagged"
                           class="rounded text-red-500 w-4 h-4">
                    <span class="text-sm font-medium text-gray-700">Tandai sebagai postingan penting/terpantau</span>
                </label>
                <div x-show="isFlagged" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan Flag</label>
                    <input type="text" name="flag_reason"
                           value="{{ old('flag_reason', $post?->flag_reason) }}"
                           placeholder="Tuliskan alasan penandaan…"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <a href="{{ route('posts.index') }}"
               class="flex-1 text-center border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2.5 rounded-lg transition">
                Batal
            </a>
            <button type="submit"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                {{ $post ? 'Perbarui Postingan' : 'Simpan Postingan' }}
            </button>
        </div>
    </div>
</form>
</div>

@push('scripts')
<script>
function postForm() {
    return {
        content: @js(old('content', $post?->content ?? '')),
        isFlagged: {{ old('is_flagged', $post?->is_flagged ?? false) ? 'true' : 'false' }},
        detectedKeywords: [],
        newTag: '',

        detectKeywords() {
            if (!this.content.trim()) return;
            fetch('{{ route('posts.detect-keywords') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ content: this.content })
            })
            .then(r => r.json())
            .then(data => { this.detectedKeywords = data.keywords; });
        },

        addTag() {
            if (!this.newTag.trim()) return;
            // Dynamically create a hidden checkbox input
            const input = document.createElement('input');
            input.type  = 'checkbox';
            input.name  = 'tags[]';
            input.value = this.newTag.trim();
            input.checked = true;
            input.classList.add('hidden');
            this.$el.closest('form').appendChild(input);
            this.newTag = '';
        }
    }
}
</script>
@endpush

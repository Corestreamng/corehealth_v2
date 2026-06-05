<footer class="footer">
    <div class="d-sm-flex justify-content-center justify-content-sm-between">
        <span class="text-muted d-block text-center text-sm-left d-sm-inline-block">{{ __('front.copyright') }} © {{date('Y')}}
            <a href="https://www.corestream.ng/" target="_blank">Corestream NG</a> </span>
        <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">{{appsettings()->footer_text ?? ''}}</span>
    </div>
</footer>

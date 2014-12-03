
	<!-- Footer -->
	<footer>
		<div class="container">
			<div class="row">
				<div class="col-md-4">
					<!-- Widget 1 -->
					<div class="widget">
						<h4>Vote for Jay</h4>
						<p>I am asking for your vote for <span class="color">Jay Whiting</span> for Minnesota House District 55A in both the primary election (Tuesday August 12, 2014 - if necessary) and on Election Day (Tuesday November 4, 2014).</p>
						<!-- Social Media -->
						<!-- TODO: Update Social Media Links -->
						<div class="social">
							<a href="https://www.facebook.com/whitingMN55A"><i class="fa fa-facebook facebook"></i></a>
							<a href="/Donate"><i class="fa fa-money money"></i></a>
							<!--<a href="#"><i class="fa fa-twitter twitter"></i></a>
							<a href="#"><i class="fa fa-pinterest pinterest"></i></a>
							<a href="#"><i class="fa fa-google-plus google-plus"></i></a>
							<a href="#"><i class="fa fa-linkedin linkedin"></i></a>-->
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<!-- widget 2 -->
					<div class="widget">
						<h4>Get Involved</h4>
						<ul>
							<li><i class="fa fa-angle-right"></i> <a href="/Events">Upcoming Events</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="/Volunteer">Volunteer</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="/Donate">Donate</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="/Contact">Contact Us</a></li>
						</ul>
					</div>
				</div>
				<div class="col-md-4">
					<!-- Widget 3 -->
					<div class="widget">
						<h4>Issues</h4>
						<ul>
							<li><i class="fa fa-angle-right"></i> <a href="#">Aging Community</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="#">Mental Health</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="#">Natural Resources</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="#">Transit</a></li>
							<li><i class="fa fa-angle-right"></i> <a href="#">Education</a></li>
						</ul>
					</div>
				</div>
			</div>
			<div class="row">
				<hr />
				<div class="col-md-12"><p class="copy pull-left">
					<!-- Copyright information. You can remove my site link. -->
					Prepared &amp; Paid for by "Jay Whiting for Minnesota" 520 Third Ave East, Shakopee, MN 55379
				</div>
			</div>
		</div>
	</footer>		
	<!--/ Footer -->

	<!-- Scroll to top -->
	<span class="totop"><a href="#"><i class="fa fa-angle-up"></i></a></span> 

	<!-- Javascript files -->
	<!-- jQuery -->
	<script src="/~Steven/won-data-extractor/js/jquery.js"></script>
	<!-- Bootstrap JS -->
	<script src="/~Steven/won-data-extractor/js/bootstrap.min.js"></script>
	<!-- Isotope, Pretty Photo JS -->
	<script src="/~Steven/won-data-extractor/js/jquery.isotope.js"></script>
	<script src="/~Steven/won-data-extractor/js/jquery.prettyPhoto.js"></script>
	<!-- Support Page Filter JS -->
	<script src="/~Steven/won-data-extractor/js/filter.js"></script>
	<!-- Flex slider JS -->
	<script src="/~Steven/won-data-extractor/js/jquery.flexslider-min.js"></script>
	<!-- Respond JS for IE8 -->
	<script src="/~Steven/won-data-extractor/js/respond.min.js"></script>
	<!-- HTML5 Support for IE -->
	<script src="/~Steven/won-data-extractor/js/html5shiv.js"></script>
	<!-- Custom JS -->
	<script src="/~Steven/won-data-extractor/js/custom.js"></script>
	<script>
	/* Flex Slider */

	$(window).load(function() {
		$('.flexslider').flexslider({
			animation: "slide",
			controlNav: true,
			pauseOnHover: true,
			slideshowSpeed: 15000
		});
		
		$('a[href="/' + location.pathname.split("/")[1] + '"]').parent().addClass('active');
	});
	</script>
</body>	
</html>
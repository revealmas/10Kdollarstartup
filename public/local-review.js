const stop=new Set('the and for with from this that your have has are was were will would should could into about their there these those role work years year experience skills skill candidate sample illustrative posting real applicant looking useful proficient preparation team our you can all job not who relevant supporting support prepared managed including provide required requirements responsibilities must more strong excellent ability using across employer available knowledge'.split(' '));
export function localReview(resume,description){
  const words=text=>[...new Set((text.toLowerCase().match(/[a-z][a-z0-9+#.-]{2,}/g)||[]).map(x=>x.replace(/[.-]+$/,'')).filter(x=>!stop.has(x)))];
  const cv=new Set(words(resume)); const desired=words(description).slice(0,120);
  const present=desired.filter(word=>cv.has(word)).slice(0,12);const missing=desired.filter(word=>!cv.has(word)).slice(0,12);
  return {summary:present.length?`Your CV includes ${present.length} of the posting’s highlighted words. Start with the evidence behind them.`:'Start by identifying the experience in your CV that relates to this posting.',
    present:present.length?present:['No exact word matches found. Check equivalent wording and related experience.'],
    missing:missing.length?missing:['No additional words identified by this simple comparison. Review the full requirements.']};
}

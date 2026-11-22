SELECT a.nokontrak, a.kdprd, a.kdloc, b.gunadeb, a.tglwo, a.tgleff,a.tglexp, a.osmdlc, a.tgkmdl, 
iif(a.haritgkmdl>a.haritgkmgn,a.haritgkmdl,a.haritgkmgn) as haritgk, a.colbaru, a.pokpby,a.stsrec, a.stsacc, a.ppap,periode  FROM TOFLMBEOM a
left join toflmb b
on a.nokontrak = b.nokontrak
where 
periode>='202608' and a.osmdlc+a.osmgnc>0 and a.kdprd<>'' and a.stsrec<>'L' and a.pokpby<>'11'



SELECT nokontrak, noreg, urut, jnsjamin, nomlikuid, nomtaksasi, tgltaks2, 1 as aktif FROM TOFJAMIN where nokontrak<>''


